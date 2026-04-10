<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

use Evenement\EventEmitter;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use VergilLai\WecomAiBot\Types\WsClientOptions;
use VergilLai\WecomAiBot\Types\WsFrame;
use VergilLai\WecomAiBot\Types\WsCmd;
use VergilLai\WecomAiBot\Types\DownloadFileResult;
use VergilLai\WecomAiBot\Types\TemplateCard;
use VergilLai\WecomAiBot\Types\ReplyMsgItem;
use VergilLai\WecomAiBot\Types\ReplyFeedback;
use VergilLai\WecomAiBot\Types\WeComMediaType;
use VergilLai\WecomAiBot\Types\UploadMediaOptions;
use VergilLai\WecomAiBot\Types\UploadMediaFinishResult;

/**
 * 企业微信智能机器人 WebSocket 客户端
 */
class WSClient extends EventEmitter
{
    private LoggerInterface $logger;
    private WsConnectionManager $connectionManager;
    private WeComApiClient $apiClient;
    private MessageHandler $messageHandler;

    public function __construct(WsClientOptions|array $options)
    {
        if (is_array($options)) {
            $options = new WsClientOptions(...$options);
        }

        $this->logger = $options->logger ?? new DefaultLogger();
        $this->apiClient = new WeComApiClient($options->requestTimeout);
        $this->messageHandler = new MessageHandler($this, $this->logger);
        $this->connectionManager = new WsConnectionManager(
            $options,
            $this->logger,
            $this->messageHandler,
        );

        // 设置认证凭证（包含额外参数 scene、plug_version）
        $extraAuthParams = array_filter([
            'scene' => $options->scene,
            'plug_version' => $options->plugVersion,
        ], fn($v) => $v !== null);
        $this->connectionManager->setCredentials($options->botId, $options->secret, $extraAuthParams);

        // 绑定 WebSocket 事件回调 → emit 到 EventEmitter
        $this->connectionManager->onConnected = function () {
            $this->emit('connected');
        };
        $this->connectionManager->onAuthenticated = function () {
            $this->logger->info('Authenticated successfully');
            $this->emit('authenticated');
        };
        $this->connectionManager->onDisconnected = function (string $reason) {
            $this->emit('disconnected', [$reason]);
        };
        $this->connectionManager->onServerDisconnect = function (string $reason) {
            $this->emit('disconnected', [$reason]);
        };
        $this->connectionManager->onReconnecting = function (int $attempt) {
            $this->emit('reconnecting', [$attempt]);
        };
        $this->connectionManager->onError = function (\Throwable $error) {
            $this->emit('error', [$error]);
        };
        $this->connectionManager->onMessage = function (WsFrame $frame) {
            $this->messageHandler->dispatch($frame);
        };
    }

    /**
     * 移除事件监听器
     *
     * @param string $event 事件名
     * @param callable|null $listener 回调函数，null 表示移除该事件的所有监听器
     * @return $this
     */
    public function off(string $event, ?callable $listener = null): static
    {
        if ($listener === null) {
            $this->removeAllListeners($event);
        } else {
            $this->removeListener($event, $listener);
        }
        return $this;
    }

    /**
     * 建立 WebSocket 连接
     *
     * @return $this
     */
    public function connect(): static
    {
        $this->connectionManager->connect();
        return $this;
    }

    /**
     * 断开连接
     */
    public function disconnect(): void
    {
        $this->connectionManager->disconnect();
    }

    /**
     * 通过 WebSocket 通道发送回复消息
     *
     * @param string $reqId 请求 ID
     * @param array $body 回复消息体
     * @param string $cmd 命令类型
     * @param int $timeout 超时时间（毫秒）
     * @return PromiseInterface<WsFrame>
     * @throws \RuntimeException
     */
    private function sendReply(string $reqId, array $body, string $cmd, int $timeout = 10000): PromiseInterface
    {
        return $this->sendReplyAsync($reqId, $body, $cmd, $timeout);
    }

    /**
     * 回复消息
     *
     * @param WsFrame $frame 收到的帧
     * @param array $body 消息体
     * @param string|null $cmd 命令类型
     * @return PromiseInterface<WsFrame>
     */
    public function reply(WsFrame $frame, array $body, ?string $cmd = null): PromiseInterface
    {
        $reqId = $frame->headers->reqId ?? Utils::generateReqId('reply');
        $cmd = $cmd ?? WsCmd::RESPONSE->value;

        return $this->sendReply($reqId, $body, $cmd);
    }

    /**
     * 发送流式文本回复
     *
     * @param WsFrame $frame 收到的帧
     * @param string $streamId 流式消息 ID
     * @param string $content 回复内容
     * @param bool $finish 是否结束流
     * @param array<ReplyMsgItem>|null $msgItem 图文混排项
     * @param ReplyFeedback|null $feedback 反馈信息
     * @return PromiseInterface<WsFrame>
     */
    public function replyStream(
        WsFrame $frame,
        string $streamId,
        string $content,
        bool $finish = false,
        ?array $msgItem = null,
        ?ReplyFeedback $feedback = null,
    ): PromiseInterface {
        $body = [
            'msgtype' => 'stream',
            'stream' => [
                'id' => $streamId,
                'finish' => $finish,
                'content' => $content,
            ],
        ];

        if ($msgItem !== null && $finish) {
            $body['stream']['msg_item'] = array_map(
                fn(ReplyMsgItem $item) => $item->toArray(),
                $msgItem
            );
        }

        if ($feedback !== null) {
            $body['stream']['feedback'] = $feedback->toArray();
        }

        $reqId = $frame->headers->reqId ?? Utils::generateReqId('reply');
        return $this->sendReplyAsync($reqId, $body, WsCmd::RESPONSE->value);
    }

    /**
     * 发送欢迎语回复
     *
     * @param WsFrame $frame 收到的帧
     * @param array $body 消息体
     * @return PromiseInterface<WsFrame>
     */
    public function replyWelcome(WsFrame $frame, array $body): PromiseInterface
    {
        return $this->reply($frame, $body, WsCmd::RESPONSE_WELCOME->value);
    }

    /**
     * 回复模板卡片消息
     *
     * @param WsFrame $frame 收到的帧
     * @param TemplateCard $templateCard 模板卡片
     * @param ReplyFeedback|null $feedback 反馈信息
     * @return PromiseInterface<WsFrame>
     */
    public function replyTemplateCard(
        WsFrame $frame,
        TemplateCard $templateCard,
        ?ReplyFeedback $feedback = null,
    ): PromiseInterface {
        $body = $templateCard->toArray();
        if ($feedback !== null) {
            $body['feedback'] = $feedback->toArray();
        }

        return $this->reply($frame, [
            'msgtype' => 'template_card',
            'template_card' => $body,
        ]);
    }

    /**
     * 流式消息 + 模板卡片组合回复
     *
     * @param WsFrame $frame 收到的帧
     * @param string $streamId 流式消息 ID
     * @param string $content 回复内容
     * @param bool $finish 是否结束流
     * @param array|null $options 选项
     * @return PromiseInterface<WsFrame>
     */
    public function replyStreamWithCard(
        WsFrame $frame,
        string $streamId,
        string $content,
        bool $finish = false,
        ?array $options = null,
    ): PromiseInterface {
        $body = [
            'msgtype' => 'stream_with_template_card',
            'stream' => [
                'id' => $streamId,
                'finish' => $finish,
                'content' => $content,
            ],
        ];

        if ($options !== null) {
            if (isset($options['msgItem']) && $finish) {
                $body['stream']['msg_item'] = array_map(
                    fn(ReplyMsgItem $item) => $item->toArray(),
                    $options['msgItem']
                );
            }
            if (isset($options['streamFeedback'])) {
                $body['stream']['feedback'] = $options['streamFeedback']->toArray();
            }
            if (isset($options['templateCard'])) {
                $card = $options['templateCard']->toArray();
                if (isset($options['cardFeedback'])) {
                    $card['feedback'] = $options['cardFeedback']->toArray();
                }
                $body['template_card'] = $card;
            }
        }

        $reqId = $frame->headers->reqId ?? Utils::generateReqId('reply');
        return $this->sendReplyAsync($reqId, $body, WsCmd::RESPONSE->value);
    }

    /**
     * 更新模板卡片
     *
     * @param WsFrame $frame 收到的帧
     * @param TemplateCard $templateCard 模板卡片
     * @param array<string>|null $userIds 要替换的用户 ID 列表
     * @return PromiseInterface<WsFrame>
     * @throws \RuntimeException
     */
    public function updateTemplateCard(
        WsFrame $frame,
        TemplateCard $templateCard,
        ?array $userIds = null,
    ): \React\Promise\PromiseInterface {
        $body = [
            'response_type' => 'update_template_card',
            'template_card' => $templateCard->toArray(),
        ];

        if ($userIds !== null) {
            $body['userids'] = $userIds;
        }

        return $this->reply($frame, $body, WsCmd::RESPONSE_UPDATE->value);
    }

    /**
     * 主动发送消息
     *
     * @param string $chatId 会话 ID
     * @param array $body 消息体
     * @return PromiseInterface<WsFrame>
     */
    public function sendMessage(string $chatId, array $body): PromiseInterface
    {
        return $this->sendReply(
            Utils::generateReqId('send'),
            array_merge(['chatid' => $chatId], $body),
            WsCmd::SEND_MSG->value
        );
    }

    /**
     * 上传临时素材（Promise 链式调用）
     *
     * @param string $filePath 文件路径
     * @param UploadMediaOptions $options 上传选项
     * @return PromiseInterface<UploadMediaFinishResult>
     */
    public function uploadMedia(string $filePath, UploadMediaOptions $options): PromiseInterface
    {
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            return \React\Promise\reject(new \RuntimeException('Cannot read file: ' . $filePath));
        }

        $chunkSize = 512 * 1024; // 512KB
        $totalChunks = Utils::calculateChunkCount($fileSize, $chunkSize);

        if ($totalChunks > 100) {
            return \React\Promise\reject(new \RuntimeException("File too large: {$totalChunks} chunks exceeds maximum of 100 chunks (max ~50MB)"));
        }

        $md5 = md5_file($filePath);
        $this->logger->info("Uploading media: type={$options->type->value}, filename={$options->filename}, size={$fileSize}, chunks={$totalChunks}");

        // Step 1: 初始化上传
        $initReqId = Utils::generateReqId('upload_init');
        return $this->sendReplyAsync($initReqId, [
            'type' => $options->type->value,
            'filename' => $options->filename,
            'total_size' => $fileSize,
            'total_chunks' => $totalChunks,
            'md5' => $md5,
        ], WsCmd::UPLOAD_MEDIA_INIT->value)->then(function (WsFrame $initResult) use ($filePath, $options, $fileSize, $chunkSize, $totalChunks) {
            $uploadId = $initResult->body['upload_id'] ?? null;
            if (!$uploadId) {
                throw new \RuntimeException('Upload init failed: no upload_id returned');
            }
            $this->logger->info("Upload init success: upload_id={$uploadId}");

            // Step 2: 分片上传 - 递归处理
            return $this->uploadChunksRecursive($filePath, $options, $uploadId, $fileSize, $chunkSize, $totalChunks, 0);
        })->then(function ($uploadId) use ($options) {
            // Step 3: 完成上传
            $finishReqId = Utils::generateReqId('upload_finish');
            return $this->sendReplyAsync($finishReqId, [
                'upload_id' => $uploadId,
            ], WsCmd::UPLOAD_MEDIA_FINISH->value);
        })->then(function (WsFrame $finishResult) use ($options) {
            $mediaId = $finishResult->body['media_id'] ?? null;
            if (!$mediaId) {
                throw new \RuntimeException('Upload finish failed: no media_id returned');
            }
            $this->logger->info("Upload complete: media_id={$mediaId}, type={$finishResult->body['type']}");
            return new UploadMediaFinishResult(
                type: WeComMediaType::from($finishResult->body['type'] ?? $options->type->value),
                mediaId: $mediaId,
                createdAt: (int) ($finishResult->body['created_at'] ?? time()),
            );
        });
    }

    /**
     * 递归上传分片
     */
    private function uploadChunksRecursive(
        string $filePath,
        UploadMediaOptions $options,
        string $uploadId,
        int $fileSize,
        int $chunkSize,
        int $totalChunks,
        int $currentChunk
    ): PromiseInterface {
        if ($currentChunk >= $totalChunks) {
            $this->logger->info("All {$totalChunks} chunks uploaded, finishing...");
            return \React\Promise\resolve($uploadId);
        }

        $start = $currentChunk * $chunkSize;
        $end = min($start + $chunkSize, $fileSize);

        $handle = fopen($filePath, 'rb');
        fseek($handle, $start);
        $chunk = fread($handle, $end - $start);
        fclose($handle);

        if ($chunk === false) {
            return \React\Promise\reject(new \RuntimeException('Failed to read chunk ' . $currentChunk));
        }

        $base64Data = base64_encode($chunk);
        $chunkReqId = Utils::generateReqId('upload_chunk');

        return $this->sendReplyAsync($chunkReqId, [
            'upload_id' => $uploadId,
            'chunk_index' => $currentChunk,
            'base64_data' => $base64Data,
        ], WsCmd::UPLOAD_MEDIA_CHUNK->value)->then(function () use ($filePath, $options, $uploadId, $fileSize, $chunkSize, $totalChunks, $currentChunk) {
            $this->logger->debug("Uploaded chunk " . ($currentChunk + 1) . "/{$totalChunks}");
            $this->emit('uploadProgress', [
                'chunkIndex' => $currentChunk,
                'totalChunks' => $totalChunks,
                'uploadId' => $uploadId,
            ]);
            return $this->uploadChunksRecursive($filePath, $options, $uploadId, $fileSize, $chunkSize, $totalChunks, $currentChunk + 1);
        });
    }

    /**
     * 异步发送请求并等待响应（ReactPHP Promise）
     *
     * @param string $reqId 请求 ID
     * @param array $body 请求体
     * @param string $cmd 命令类型
     * @param int $timeout 超时时间（毫秒）
     * @return PromiseInterface<WsFrame>
     */
    private function sendReplyAsync(string $reqId, array $body, string $cmd, int $timeout = 10000): PromiseInterface
    {
        return $this->connectionManager->sendReply($reqId, $body, $cmd, $timeout)
            ->then(function (WsFrame $response) {
                // 错误码检查
                if ($response->errcode !== 0 && $response->errcode !== null) {
                    throw new \RuntimeException($response->errmsg ?? 'Request failed with errcode ' . $response->errcode);
                }
                return $response;
            });
    }

    /**
     * 被动回复媒体消息
     *
     * @param WsFrame $frame 收到的帧
     * @param WeComMediaType $mediaType 媒体类型
     * @param string $mediaId 媒体 ID
     * @param array|null $videoOptions 视频选项
     * @return WsFrame
     * @throws \RuntimeException
     */
    public function replyMedia(
        WsFrame $frame,
        WeComMediaType $mediaType,
        string $mediaId,
        ?array $videoOptions = null,
    ): PromiseInterface {
        $body = [
            'msgtype' => $mediaType->value,
            $mediaType->value => ['media_id' => $mediaId],
        ];

        if ($mediaType === WeComMediaType::Video && $videoOptions !== null) {
            $body['video'] = array_merge($body['video'], $videoOptions);
        }

        return $this->reply($frame, $body);
    }

    /**
     * 主动发送媒体消息
     *
     * @param string $chatId 会话 ID
     * @param WeComMediaType $mediaType 媒体类型
     * @param string $mediaId 媒体 ID
     * @param array|null $videoOptions 视频选项
     * @return PromiseInterface<WsFrame>
     */
    public function sendMediaMessage(
        string $chatId,
        WeComMediaType $mediaType,
        string $mediaId,
        ?array $videoOptions = null,
    ): PromiseInterface {
        $body = [
            'msgtype' => $mediaType->value,
            $mediaType->value => ['media_id' => $mediaId],
        ];

        if ($mediaType === WeComMediaType::Video && $videoOptions !== null) {
            $body['video'] = array_merge($body['video'], $videoOptions);
        }

        return $this->sendMessage($chatId, $body);
    }

    /**
     * 下载文件并解密
     *
     * @param string $url 下载地址
     * @param string|null $aesKey 解密密钥（可选，不传则返回原始数据）
     * @return DownloadFileResult
     * @throws \RuntimeException
     */
    public function downloadFile(string $url, ?string $aesKey = null): DownloadFileResult
    {
        return $this->apiClient->downloadFile($url, $aesKey);
    }

    /**
     * 获取消息流（ReactPHP Stream）
     *
     * 返回一个可遍历的消息流，使用 on('message', $callback) 监听消息
     *
     * @return PromiseInterface
     */
    public function messages(): PromiseInterface
    {
        return new Promise(function ($resolve) {
            $this->on('message', function (WsFrame $frame) use ($resolve) {
                $resolve($frame);
            });
        });
    }

    /**
     * 获取 API 客户端（高级用途）
     */
    public function getApiClient(): WeComApiClient
    {
        return $this->apiClient;
    }

    /**
     * 获取连接管理器（高级用途）
     */
    public function getConnectionManager(): WsConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * 检查是否已连接
     */
    public function isConnected(): bool
    {
        return $this->connectionManager->isRunning();
    }
}
