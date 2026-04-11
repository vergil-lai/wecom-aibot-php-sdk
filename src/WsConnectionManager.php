<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector as WsConnector;
use VergilLai\WecomAiBot\Exceptions\WSAuthFailureError;
use VergilLai\WecomAiBot\Exceptions\WSReconnectExhaustedError;
use VergilLai\WecomAiBot\Types\WsClientOptions;
use VergilLai\WecomAiBot\Types\WsFrame;
use VergilLai\WecomAiBot\Types\WsFrameHeaders;
use VergilLai\WecomAiBot\Types\WsCmd;

/**
 * WebSocket 连接管理器（基于 ratchet/pawl）
 */
class WsConnectionManager
{
    private ?WebSocket $connection = null;
    private int $reconnectAttempts = 0;
    private int $authFailureAttempts = 0;
    private bool $isRunning = false;
    private bool $isManualClose = false;
    private bool $lastCloseWasAuthFailure = false;
    private int $missedPongCount = 0;
    private const MAX_MISSED_PONG = 2;
    private const RECONNECT_BASE_DELAY = 1000;
    private const RECONNECT_MAX_DELAY = 30000;
    private ?TimerInterface $heartbeatTimer = null;
    private ?TimerInterface $reconnectTimer = null;

    public $onConnected = null;
    public $onAuthenticated = null;
    public $onDisconnected = null;
    public $onMessage = null;
    public $onReconnecting = null;
    public $onError = null;
    public $onServerDisconnect = null;

    /** 认证凭证 */
    private string $botId = '';
    private string $botSecret = '';
    private array $extraAuthParams = [];

    /** 回复队列：reqId -> 队列 */
    private array $replyQueues = [];

    /** 等待回执：reqId -> pending info */
    private array $pendingAcks = [];

    /** 回执序列号计数器 */
    private int $pendingAckSeq = 0;

    /** 回执超时时间（毫秒） */
    private int $replyAckTimeout = 5000;

    /** 单个 reqId 队列最大长度 */
    private int $maxReplyQueueSize;

    private LoggerInterface $logger;
    private WsClientOptions $options;

    public function __construct(
        WsClientOptions $options,
        LoggerInterface $logger,
        private readonly MessageHandler $messageHandler,
    ) {
        $this->options = $options;
        $this->logger = $logger;
        $this->maxReplyQueueSize = $options->maxReplyQueueSize;
    }

    /**
     * 设置认证凭证
     */
    public function setCredentials(string $botId, string $botSecret, array $extraAuthParams = []): void
    {
        $this->botId = $botId;
        $this->botSecret = $botSecret;
        $this->extraAuthParams = $extraAuthParams;
    }

    /**
     * 启动 WebSocket 连接
     */
    public function connect(): void
    {
        $this->isManualClose = false;
        $this->isRunning = true;

        // 取消挂起的重连定时器
        if ($this->reconnectTimer !== null) {
            Loop::cancelTimer($this->reconnectTimer);
            $this->reconnectTimer = null;
        }

        // 清理旧连接
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->connectInternal();
    }

    /**
     * 内部连接方法
     */
    private function connectInternal(): void
    {
        $wsUrl = $this->options->wsUrl;
        $this->logger->info('Connecting to WebSocket: ' . $wsUrl . '...');

        $connector = new WsConnector(Loop::get());

        $connector($wsUrl)->then(
            function (WebSocket $conn) {
                $this->handleConnect($conn);
            },
            function (\Throwable $e) {
                $this->logger->error('WebSocket connection failed: ' . $e->getMessage());
                $this->scheduleReconnect();
            }
        );
    }

    /**
     * 处理连接建立
     */
    private function handleConnect(WebSocket $conn): void
    {
        $this->connection = $conn;
        $this->missedPongCount = 0;
        $this->lastCloseWasAuthFailure = false;

        $this->logger->info('WebSocket connection established, sending auth...');
        $this->emitConnected();

        $conn->on('message', function ($msg) {
            $data = (string) $msg;
            $this->handleMessage($data);
        });

        $conn->on('close', function ($code, $reason) {
            $reasonStr = $reason ?: "code: {$code}";
            $this->logger->warning('WebSocket disconnected: ' . $reasonStr);
            $this->stopHeartbeat();
            $this->clearPendingMessages('WebSocket connection closed (' . $reasonStr . ')');
            $this->connection = null;
            $this->emitDisconnected($reasonStr);
        });

        $conn->on('error', function (\Throwable $e) {
            $this->logger->error('WebSocket error: ' . $e->getMessage());
            $this->emitError($e);
        });

        $this->sendAuth();
    }

    /**
     * 发送认证帧
     */
    private function sendAuth(): void
    {
        $frame = new WsFrame(
            cmd: WsCmd::SUBSCRIBE->value,
            headers: new WsFrameHeaders(
                reqId: Utils::generateReqId(WsCmd::SUBSCRIBE->value),
            ),
            body: array_merge([
                'bot_id' => $this->botId,
                'secret' => $this->botSecret,
            ], $this->extraAuthParams),
        );

        $this->sendFrame($frame);
        $this->logger->info('Auth frame sent');
    }

    /**
     * 处理接收到的消息
     */
    private function handleMessage(string $data): void
    {
        $frame = $this->messageHandler->parseFrame($data);
        if ($frame === null) {
            $this->logger->warning('Failed to parse message');
            return;
        }

        $cmd = $frame->cmd ?? '';
        $reqId = $frame->headers->reqId ?? '';

        // 消息推送
        if ($cmd === WsCmd::CALLBACK->value) {
            $this->logger->debug('[server -> plugin] cmd=' . $cmd . ', reqId=' . $reqId);
            $this->emitMessage($frame);
            return;
        }

        // 事件推送
        if ($cmd === WsCmd::EVENT_CALLBACK->value) {
            $this->logger->debug('[server -> plugin] cmd=' . $cmd . ', reqId=' . $reqId);

            // 检测 disconnected_event
            if (($frame->body['event']['eventtype'] ?? '') === 'disconnected_event') {
                $this->logger->warning('Received disconnected_event: a new connection has been established');
                $this->stopHeartbeat();
                $this->clearPendingMessages('Server disconnected due to new connection');
                $this->isManualClose = true;
                $this->emitServerDisconnect();
                if ($this->connection !== null) {
                    $this->connection->close();
                    $this->connection = null;
                }
                return;
            }

            $this->emitMessage($frame);
            return;
        }

        // 认证响应
        if (strpos($reqId, WsCmd::SUBSCRIBE->value) === 0) {
            if (($frame->errcode ?? 0) !== 0) {
                $this->logger->error('Authentication failed: errcode=' . ($frame->errcode ?? 0) . ', errmsg=' . ($frame->errmsg ?? ''));
                $this->lastCloseWasAuthFailure = true;
                $this->emitError(new \RuntimeException('Authentication failed: ' . ($frame->errmsg ?? '')));
                if ($this->connection !== null) {
                    $this->connection->close();
                    $this->connection = null;
                }
                return;
            }
            $this->logger->info('Authentication successful');
            $this->reconnectAttempts = 0;
            $this->authFailureAttempts = 0;
            $this->startHeartbeat();
            $this->emitAuthenticated();
            return;
        }

        // 心跳响应
        if (strpos($reqId, WsCmd::HEARTBEAT->value) === 0) {
            if (($frame->errcode ?? 0) !== 0) {
                $this->logger->warning('Heartbeat ack error: errcode=' . ($frame->errcode ?? 0));
                return;
            }
            $this->missedPongCount = 0;
            return;
        }

        // 检查是否是回复消息的回执
        if ($reqId !== '' && isset($this->pendingAcks[$reqId])) {
            $this->handleReplyAck($reqId, $frame);
            return;
        }

        // 未知帧
        $this->logger->warning('Received unknown frame (ignored): ' . json_encode($frame));
    }

    /**
     * 启动心跳
     */
    private function startHeartbeat(): void
    {
        $this->stopHeartbeat();
        $interval = $this->options->heartbeatInterval / 1000;

        $this->heartbeatTimer = Loop::addPeriodicTimer($interval, function () {
            $this->sendHeartbeat();
        });
        $this->logger->debug('Heartbeat timer started, interval: ' . $this->options->heartbeatInterval . 'ms');
    }

    /**
     * 停止心跳
     */
    private function stopHeartbeat(): void
    {
        if ($this->heartbeatTimer !== null) {
            Loop::cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
            $this->logger->debug('Heartbeat timer stopped');
        }
    }

    /**
     * 发送心跳
     */
    private function sendHeartbeat(): void
    {
        if ($this->missedPongCount >= self::MAX_MISSED_PONG) {
            $this->logger->warning('No heartbeat ack received for ' . $this->missedPongCount . ' consecutive pings');
            $this->stopHeartbeat();
            if ($this->connection !== null) {
                $this->connection->close();
                $this->connection = null;
            }
            return;
        }

        $this->missedPongCount++;

        try {
            $frame = new WsFrame(
                cmd: WsCmd::HEARTBEAT->value,
                headers: new WsFrameHeaders(
                    reqId: Utils::generateReqId(WsCmd::HEARTBEAT->value),
                ),
            );
            $this->sendFrame($frame);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send heartbeat: ' . $e->getMessage());
        }
    }

    /**
     * 安排重连
     */
    private function scheduleReconnect(): void
    {
        if (!$this->isRunning) {
            return;
        }

        if ($this->lastCloseWasAuthFailure) {
            // 认证失败场景
            if ($this->options->maxAuthFailureAttempts !== -1
                && $this->authFailureAttempts >= $this->options->maxAuthFailureAttempts) {
                $this->logger->error('Max auth failure attempts reached (' . $this->options->maxAuthFailureAttempts . ')');
                $this->emitError(new WSAuthFailureError($this->options->maxAuthFailureAttempts));
                return;
            }
            $this->authFailureAttempts++;

            $delay = min(
                self::RECONNECT_BASE_DELAY * pow(2, $this->authFailureAttempts - 1),
                self::RECONNECT_MAX_DELAY
            );

            $this->logger->info('Auth failed, reconnecting in ' . $delay . 'ms (attempt ' . $this->authFailureAttempts . '/' . $this->options->maxAuthFailureAttempts . ')...');
            $this->emitReconnecting($this->authFailureAttempts);
        } else {
            // 连接断开场景
            if ($this->options->maxReconnectAttempts !== -1
                && $this->reconnectAttempts >= $this->options->maxReconnectAttempts) {
                $this->logger->error('Max reconnect attempts reached (' . $this->options->maxReconnectAttempts . ')');
                $this->emitError(new WSReconnectExhaustedError($this->options->maxReconnectAttempts));
                return;
            }
            $this->reconnectAttempts++;

            $delay = min(
                self::RECONNECT_BASE_DELAY * pow(2, $this->reconnectAttempts - 1),
                self::RECONNECT_MAX_DELAY
            );

            $this->logger->info('Connection lost, reconnecting in ' . $delay . 'ms (attempt ' . $this->reconnectAttempts . '/' . $this->options->maxReconnectAttempts . ')...');
            $this->emitReconnecting($this->reconnectAttempts);
        }

        $this->reconnectTimer = Loop::addTimer($delay / 1000, function () {
            $this->reconnectTimer = null;
            if ($this->isManualClose) {
                return;
            }
            $this->connectInternal();
        });
    }

    /**
     * 发送 WebSocket 帧
     */
    private function sendFrame(WsFrame $frame): bool
    {
        if ($this->connection === null) {
            $this->logger->warning('Cannot send frame: not connected');
            return false;
        }

        try {
            $data = json_encode($frame->toArray(), JSON_THROW_ON_ERROR);
            $this->connection->send($data);
            return true;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to encode frame: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 通过 WebSocket 通道发送回复消息（队列版本）
     */
    public function sendReply(string $reqId, array $body, string $cmd = WsCmd::RESPONSE->value, int $timeout = 5000): \React\Promise\PromiseInterface
    {
        $frame = new WsFrame(
            cmd: $cmd,
            headers: new WsFrameHeaders(reqId: $reqId),
            body: $body,
        );

        $deferred = new Deferred();

        if (!isset($this->replyQueues[$reqId])) {
            $this->replyQueues[$reqId] = [];
        }

        $queue = &$this->replyQueues[$reqId];

        if (count($queue) >= $this->maxReplyQueueSize) {
            $this->logger->warning('Reply queue for reqId ' . $reqId . ' exceeds max size (' . $this->maxReplyQueueSize . ')');
            return \React\Promise\reject(new \RuntimeException('Reply queue for reqId ' . $reqId . ' exceeds max size'));
        }

        $queue[] = ['frame' => $frame, 'deferred' => $deferred, 'timeout' => $timeout];

        if (count($queue) === 1) {
            $this->processReplyQueue($reqId);
        }

        return $deferred->promise();
    }

    /**
     * 处理指定 reqId 的回复队列
     */
    private function processReplyQueue(string $reqId): void
    {
        $queue = $this->replyQueues[$reqId] ?? null;
        if ($queue === null || count($queue) === 0) {
            unset($this->replyQueues[$reqId]);
            return;
        }

        $item = $queue[0];
        $frame = $item['frame'];
        $deferred = $item['deferred'];
        $timeout = $item['timeout'] ?? $this->replyAckTimeout;

        try {
            $this->sendFrame($frame);
            $this->logger->debug('Reply message sent, reqId: ' . $reqId . ', queue length: ' . count($queue));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send reply for reqId ' . $reqId . ': ' . $e->getMessage());
            array_shift($this->replyQueues[$reqId]);
            $deferred->reject($e);
            $this->processReplyQueue($reqId);
            return;
        }

        $seq = ++$this->pendingAckSeq;

        $timer = Loop::addTimer($timeout / 1000, function () use ($reqId, $seq, &$deferred, $timeout) {
            $pending = $this->pendingAcks[$reqId] ?? null;
            if ($pending === null || $pending['seq'] !== $seq || ($pending['handled'] ?? false)) {
                return;
            }

            $this->pendingAcks[$reqId]['handled'] = true;

            $this->logger->warning('Reply ack timeout (' . $timeout . 'ms) for reqId: ' . $reqId);
            unset($this->pendingAcks[$reqId]);

            array_shift($this->replyQueues[$reqId]);
            $deferred->reject(new \RuntimeException('Reply ack timeout for reqId: ' . $reqId));
            $this->processReplyQueue($reqId);
        });

        $this->pendingAcks[$reqId] = [
            'deferred' => $deferred,
            'timer' => $timer,
            'seq' => $seq,
            'handled' => false,
        ];
    }

    /**
     * 处理回复消息的回执
     */
    private function handleReplyAck(string $reqId, WsFrame $frame): void
    {
        $pending = $this->pendingAcks[$reqId] ?? null;
        if ($pending === null || ($pending['handled'] ?? false)) {
            return;
        }

        $this->pendingAcks[$reqId]['handled'] = true;

        Loop::cancelTimer($pending['timer']);
        unset($this->pendingAcks[$reqId]);

        $queue = &$this->replyQueues[$reqId];
        if ($queue !== null && count($queue) > 0) {
            array_shift($queue);
        }

        if (($frame->errcode ?? 0) !== 0) {
            $this->logger->warning('Reply ack error: reqId=' . $reqId . ', errcode=' . ($frame->errcode ?? 0) . ', errmsg=' . ($frame->errmsg ?? ''));
            $pending['deferred']->reject(new \RuntimeException($frame->errmsg ?? 'Reply failed with errcode ' . $frame->errcode));
        } else {
            $this->logger->debug('Reply ack received for reqId: ' . $reqId);
            $pending['deferred']->resolve($frame);
        }

        $this->processReplyQueue($reqId);
    }

    /**
     * 清理所有待处理的消息和回执
     */
    private function clearPendingMessages(string $reason): void
    {
        $pendingRejects = [];
        foreach ($this->pendingAcks as $reqId => $pending) {
            Loop::cancelTimer($pending['timer']);
            $pendingRejects[] = $pending['deferred'];
            $pending['deferred']->reject(new \RuntimeException($reason . ', reply for reqId: ' . $reqId . ' cancelled'));
        }
        $this->pendingAcks = [];

        foreach ($this->replyQueues as $reqId => $queue) {
            foreach ($queue as $item) {
                $alreadyRejected = false;
                foreach ($pendingRejects as $pr) {
                    if ($pr === $item['deferred']) {
                        $alreadyRejected = true;
                        break;
                    }
                }
                if (!$alreadyRejected) {
                    $item['deferred']->reject(new \RuntimeException($reason . ', reply for reqId: ' . $reqId . ' cancelled'));
                }
            }
        }
        $this->replyQueues = [];
    }

    /**
     * 断开连接
     */
    public function disconnect(): void
    {
        $this->isManualClose = true;
        $this->isRunning = false;
        $this->stopHeartbeat();
        $this->clearPendingMessages('Connection manually closed');

        if ($this->reconnectTimer !== null) {
            Loop::cancelTimer($this->reconnectTimer);
            $this->reconnectTimer = null;
        }

        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->logger->info('WebSocket connection manually closed');
    }

    // ========== 事件回调 ==========

    private function emitConnected(): void
    {
        if ($this->onConnected !== null) {
            ($this->onConnected)();
        }
    }

    private function emitAuthenticated(): void
    {
        if ($this->onAuthenticated !== null) {
            ($this->onAuthenticated)();
        }
    }

    private function emitDisconnected(string $reason = 'Connection closed'): void
    {
        if ($this->onDisconnected !== null) {
            ($this->onDisconnected)($reason);
        }
        if (!$this->isManualClose) {
            $this->scheduleReconnect();
        }
    }

    private function emitServerDisconnect(string $reason = 'New connection established, server disconnected this connection'): void
    {
        if ($this->onServerDisconnect !== null) {
            ($this->onServerDisconnect)($reason);
        }
    }

    private function emitReconnecting(int $attempt): void
    {
        if ($this->onReconnecting !== null) {
            ($this->onReconnecting)($attempt);
        }
    }

    private function emitError(\Throwable $error): void
    {
        if ($this->onError !== null) {
            ($this->onError)($error);
        }
    }

    private function emitMessage(WsFrame $frame): void
    {
        if ($this->onMessage !== null) {
            ($this->onMessage)($frame);
        }
    }

    /**
     * 检查是否已连接
     */
    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * 检查是否正在运行
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }
}
