<?php
/**
 * 企业微信智能机器人 SDK 基本使用示例
 *
 * 运行方式: php examples/basic.php
 */
require_once __DIR__ . '/../vendor/autoload.php';

use VergilLai\WecomAiBot\WSClient;
use VergilLai\WecomAiBot\Types\WsClientOptions;
use VergilLai\WecomAiBot\Types\WsFrame;
use VergilLai\WecomAiBot\Utils;

// 创建 WSClient 实例
$wsClient = new WSClient(new WsClientOptions(
    botId: 'your-bot-id',
    secret: 'your-bot-secret',
));

$templateCard = [
    'card_type' => 'multiple_interaction',
    'source' => [
        'icon_url' => 'https://wework.qpic.cn/wwpic/252813_jOfDHtcISzuodLa_1629280209/0',
        'desc' => '企业微信',
    ],
    'main_title' => [
        'title' => '欢迎使用企业微信',
        'desc' => '您的好友正在邀请您加入企业微信',
    ],
    'select_list' => [
        [
            'question_key' => 'question_key_one',
            'title' => '选择标签1',
            'disable' => false,
            'selected_id' => 'id_one',
            'option_list' => [
                ['id' => 'id_one', 'text' => '选择器选项1'],
                ['id' => 'id_two', 'text' => '选择器选项2'],
            ],
        ],
        [
            'question_key' => 'question_key_two',
            'title' => '选择标签2',
            'selected_id' => 'id_three',
            'option_list' => [
                ['id' => 'id_three', 'text' => '选择器选项3'],
                ['id' => 'id_four', 'text' => '选择器选项4'],
            ],
        ],
    ],
    'submit_button' => [
        'text' => '提交',
        'key' => 'submit_key',
    ],
    'task_id' => 'task_id_' . time(),
];

// 建立连接
$wsClient->connect();

// 监听连接事件
$wsClient->on('connected', function () {
    echo "✅ WebSocket 已连接\n";
});

// 监听认证成功事件
$wsClient->on('authenticated', function () {
    echo "🔐 认证成功\n";
});

// 监听断开事件
$wsClient->on('disconnected', function (string $reason) {
    echo "❌ 连接已断开: {$reason}\n";
});

// 监听重连事件
$wsClient->on('reconnecting', function (int $attempt) {
    echo "🔄 正在进行第 {$attempt} 次重连...\n";
});

// 监听错误事件
$wsClient->on('error', function (\Throwable $error) {
    echo "⚠️ 发生错误: {$error->getMessage()}\n";
});

// 监听所有消息
$wsClient->on('message', function (WsFrame $frame) {
    $body = json_encode($frame->body, JSON_UNESCAPED_UNICODE);
    echo "📨 收到消息: " . mb_substr($body, 0, 200) . "\n";
});

// 监听文本消息，使用流式回复
$wsClient->on('message.text', function (WsFrame $frame) use ($wsClient, $templateCard) {
    $body = $frame->body;
    echo "📝 收到文本消息: " . ($body['text']['content'] ?? '') . "\n";

    // 生成一个流式消息 ID
    $streamId = Utils::generateReqId('stream');

    // 测试主动发送消息（将 CHATID 替换为实际的会话 ID）
    // $wsClient->sendMessage($body['from']['userid'] ?? '', [
    //     'msgtype' => 'markdown',
    //     'markdown' => ['content' => '这是一条**主动推送**的消息'],
    // ]);

    // 发送流式中间内容
    $wsClient->replyStream($frame, $streamId, '正在思考中...', false);

    // 模拟异步处理后发送最终结果
    React\EventLoop\Loop::addTimer(2.0, function () use ($wsClient, $frame, $streamId, $body) {
        $wsClient->replyStream($frame, $streamId, '你好！你说的是', false);
    });

    React\EventLoop\Loop::addTimer(3.0, function () use ($wsClient, $frame, $streamId, $body) {
        $content = $body['text']['content'] ?? '';
        $wsClient->replyStream($frame, $streamId, "你好！你说的是: \"{$content}\"", true);
        echo "✅ 流式回复完成\n";
    });

    // 卡片回复 - 只发一个模板卡片
    $wsClient->replyTemplateCard($frame, \VergilLai\WecomAiBot\Types\TemplateCard::fromApiArray($templateCard));
});

// 监听图片消息，下载并解密
$wsClient->on('message.image', function (WsFrame $frame) use ($wsClient) {
    $body = $frame->body;
    $imageUrl = $body['image']['url'] ?? '';
    echo "🖼️ 收到图片消息: {$imageUrl}\n";

    if (empty($imageUrl)) return;

    try {
        // 下载图片并使用消息中的 aeskey 解密
        $aesKey = $body['image']['aeskey'] ?? null;
        $result = $wsClient->downloadFile($imageUrl, $aesKey);
        echo "✅ 图片下载成功，大小: " . strlen($result->buffer) . " bytes\n";

        // 保存到文件
        $fileName = $result->filename ?? 'image_' . time() . '.jpg';
        $savePath = __DIR__ . '/' . $fileName;
        file_put_contents($savePath, $result->buffer);
        echo "💾 图片已保存到: {$savePath}\n";
    } catch (\Throwable $e) {
        echo "❌ 图片下载失败: {$e->getMessage()}\n";
    }
});

// 监听图文混排消息
$wsClient->on('message.mixed', function (WsFrame $frame) {
    $body = $frame->body;
    $items = $body['mixed']['msg_item'] ?? [];
    echo "🖼️ 收到图文混排消息，包含 " . count($items) . " 个子项\n";

    foreach ($items as $index => $item) {
        if (($item['msgtype'] ?? '') === 'text') {
            echo "  [{$index}] 文本: " . ($item['text']['content'] ?? '') . "\n";
        } elseif (($item['msgtype'] ?? '') === 'image') {
            echo "  [{$index}] 图片: " . ($item['image']['url'] ?? '') . "\n";
        }
    }
});

// 监听语音消息
$wsClient->on('message.voice', function (WsFrame $frame) {
    $body = $frame->body;
    echo "🎙️ 收到语音消息（转文本）: " . ($body['voice']['content'] ?? '') . "\n";
});

// 监听文件消息
$wsClient->on('message.file', function (WsFrame $frame) use ($wsClient) {
    $body = $frame->body;
    $fileUrl = $body['file']['url'] ?? '';
    echo "📁 收到文件消息: {$fileUrl}\n";

    if (empty($fileUrl)) return;

    try {
        $aesKey = $body['file']['aeskey'] ?? null;
        $result = $wsClient->downloadFile($fileUrl, $aesKey);
        echo "✅ 文件下载成功，大小: " . strlen($result->buffer) . " bytes\n";

        $fileName = $result->filename ?? 'file_' . time();
        $savePath = __DIR__ . '/' . $fileName;
        file_put_contents($savePath, $result->buffer);
        echo "💾 文件已保存到: {$savePath}\n";
    } catch (\Throwable $e) {
        echo "❌ 文件下载失败: {$e->getMessage()}\n";
    }
});

// 监听进入会话事件（发送欢迎语）
$wsClient->on('event.enter_chat', function (WsFrame $frame) use ($wsClient) {
    echo "👋 用户进入会话\n";
    $wsClient->replyWelcome($frame, [
        'msgtype' => 'text',
        'text' => ['content' => '您好！我是智能助手，有什么可以帮您的吗？'],
    ]);
});

// 监听模板卡片事件
$wsClient->on('event.template_card_event', function (WsFrame $frame) {
    $body = $frame->body;
    echo "🃏 收到模板卡片事件: " . ($body['event']['event_key'] ?? '') . "\n";
});

// 监听用户反馈事件
$wsClient->on('event.feedback_event', function (WsFrame $frame) {
    $body = $frame->body;
    echo "💬 收到用户反馈事件: " . json_encode($body['event'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
});

// 优雅退出
$loop = React\EventLoop\Loop::get();
$loop->addSignal(SIGINT, function () use ($wsClient, $loop) {
    echo "\n正在停止机器人...\n";
    $wsClient->disconnect();
    $loop->stop();
});

$loop->addSignal(SIGTERM, function () use ($wsClient, $loop) {
    $wsClient->disconnect();
    $loop->stop();
});

echo "机器人已启动，按 Ctrl+C 退出\n";
