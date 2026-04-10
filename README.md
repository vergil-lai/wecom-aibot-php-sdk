# wecom-aibot-php-sdk

[企业微信智能机器人](https://developer.work.weixin.qq.com/document/path/101463) PHP SDK —— 基于 ReactPHP WebSocket 长连接通道，提供消息收发、流式回复、模板卡片、事件回调、文件下载解密、媒体素材上传等核心能力。

本 SDK 从官方 [Node.js SDK](https://github.com/WecomTeam/aibot-node-sdk) 移植而来，API 设计保持一致。

## 特性

- **WebSocket 长连接** — 基于 `wss://openws.work.weixin.qq.com` 内置默认地址，开箱即用
- **自动认证** — 连接建立后自动发送认证帧（botId + secret）
- **心跳保活** — 自动维护心跳，连续未收到 ack 时自动判定连接异常
- **断线重连** — 指数退避重连策略（1s -> 2s -> 4s -> ... -> 30s 上限），支持自定义最大重连次数
- **消息分发** — 自动解析消息类型并触发对应事件（text / image / mixed / voice / file / video）
- **流式回复** — 内置流式回复方法，支持 Markdown 和图文混排
- **模板卡片** — 支持回复模板卡片消息、流式+卡片组合回复、更新卡片
- **主动推送** — 支持向指定会话主动发送 Markdown、模板卡片或媒体消息，无需依赖回调帧
- **事件回调** — 支持进入会话、模板卡片按钮点击、用户反馈等事件
- **串行回复队列** — 同一 req_id 的回复消息串行发送，自动等待回执
- **文件下载解密** — 内置 AES-256-CBC 文件解密，每个图片/文件消息自带独立的 aeskey
- **媒体素材上传** — 支持分片上传临时素材（file/image/voice/video），自动管理并发与重试
- **可插拔日志** — 支持自定义 Logger，内置带时间戳的 DefaultLogger
- **异步非阻塞** — 基于 ReactPHP 事件循环，单线程高并发

## 安装

```bash
composer require vergil-lai/wecom-aibot-php-sdk
```

### 系统要求

- PHP >= 8.1
- ext-openssl

## 快速开始

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use VergilLai\WecomAiBot\WSClient;
use VergilLai\WecomAiBot\Types\WsClientOptions;
use VergilLai\WecomAiBot\Types\WsFrame;
use VergilLai\WecomAiBot\Utils;

// 1. 创建客户端实例
$wsClient = new WSClient(new WsClientOptions(
    botId: 'your-bot-id',       // 企业微信后台获取的机器人 ID
    secret: 'your-bot-secret',  // 企业微信后台获取的机器人 Secret
));

// 2. 建立连接（支持链式调用）
$wsClient->connect();

// 3. 监听认证成功
$wsClient->on('authenticated', function () {
    echo '🔐 认证成功', PHP_EOL;
});

// 4. 监听文本消息并进行流式回复
$wsClient->on('message.text', function (WsFrame $frame) use ($wsClient) {
    $content = $frame->body['text']['content'] ?? '';
    echo "收到文本: {$content}", PHP_EOL;

    $streamId = Utils::generateReqId('stream');

    // 发送流式中间内容
    $wsClient->replyStream($frame, $streamId, '正在思考中...', false);

    // 发送最终结果
    React\EventLoop\Loop::addTimer(1.0, function () use ($wsClient, $frame, $streamId, $content) {
        $wsClient->replyStream($frame, $streamId, "你好！你说的是: \"{$content}\"", true);
    });
});

// 5. 监听进入会话事件（发送欢迎语）
$wsClient->on('event.enter_chat', function (WsFrame $frame) use ($wsClient) {
    $wsClient->replyWelcome($frame, [
        'msgtype' => 'text',
        'text' => ['content' => '您好！我是智能助手，有什么可以帮您的吗？'],
    ]);
});

// 6. 优雅退出
$loop = React\EventLoop\Loop::get();
$loop->addSignal(SIGINT, function () use ($wsClient, $loop) {
    $wsClient->disconnect();
    $loop->stop();
});
```

---

## API 文档

### `WSClient`

核心客户端类，继承自 `Evenement\EventEmitter`，提供连接管理、消息收发等功能。

#### 构造函数

```php
$wsClient = new WSClient(options: WsClientOptions|array);
```

#### 方法一览

| 方法                                                                  | 说明                                                   | 返回值                                      |
| --------------------------------------------------------------------- | ------------------------------------------------------ | ------------------------------------------- |
| `connect()`                                                           | 建立 WebSocket 连接，连接后自动认证                    | `$this`（支持链式调用）                     |
| `disconnect()`                                                        | 主动断开连接                                           | `void`                                      |
| `reply(frame, body, cmd?)`                                            | 通过 WebSocket 通道发送回复消息（通用方法）            | `WsFrame`                                   |
| `replyStream(frame, streamId, content, finish?, msgItem?, feedback?)` | 发送流式文本回复（支持 Markdown）                      | `WsFrame`                                   |
| `replyWelcome(frame, body)`                                           | 发送欢迎语回复（文本或模板卡片），需 5s 内调用         | `WsFrame`                                   |
| `replyTemplateCard(frame, templateCard, feedback?)`                   | 回复模板卡片消息                                       | `WsFrame`                                   |
| `replyStreamWithCard(frame, streamId, content, finish?, options?)`    | 流式消息 + 模板卡片组合回复                            | `WsFrame`                                   |
| `updateTemplateCard(frame, templateCard, userIds?)`                   | 更新模板卡片（响应 template_card_event），需 5s 内调用 | `WsFrame`                                   |
| `sendMessage(chatId, body)`                                           | 主动发送消息（Markdown / 模板卡片 / 媒体），无需回调帧 | `WsFrame`                                   |
| `uploadMedia(filePath, options)`                                      | 上传临时素材（三步分片上传），返回 `media_id`          | `PromiseInterface<UploadMediaFinishResult>` |
| `replyMedia(frame, mediaType, mediaId, videoOptions?)`                | 被动回复媒体消息（file/image/voice/video）             | `WsFrame`                                   |
| `sendMediaMessage(chatId, mediaType, mediaId, videoOptions?)`         | 主动发送媒体消息                                       | `WsFrame`                                   |
| `downloadFile(url, aesKey?)`                                          | 下载文件并 AES 解密                                    | `DownloadFileResult`                        |
| `on(event, listener)`                                                 | 注册事件监听器（继承自 EventEmitter）                  | `$this`                                     |
| `once(event, listener)`                                               | 注册一次性事件监听器                                   | `$this`                                     |
| `off(event, listener?)`                                               | 移除事件监听器                                         | `$this`                                     |

#### 属性

| 属性                     | 说明                            | 类型                  |
| ------------------------ | ------------------------------- | --------------------- |
| `isConnected()`          | 当前 WebSocket 连接状态         | `bool`                |
| `getApiClient()`         | 内部 API 客户端实例（高级用途） | `WeComApiClient`      |
| `getConnectionManager()` | 连接管理器（高级用途）          | `WsConnectionManager` |

---

### `replyStream` 详细说明

发送流式文本回复（便捷方法，支持 Markdown）。

```php
$wsClient->replyStream(
    frame: WsFrame,              // 收到的原始 WebSocket 帧（透传 req_id）
    streamId: string,            // 流式消息 ID（使用 Utils::generateReqId('stream') 生成）
    content: string,             // 回复内容（支持 Markdown），最长 20480 字节
    finish: bool = false,        // 是否结束流式消息
    msgItem: ?array = null,      // 图文混排项（仅 finish=true 时有效，最多 10 个）
    feedback: ?ReplyFeedback = null, // 反馈信息（仅首次回复时设置）
);
```

使用示例：

```php
$streamId = Utils::generateReqId('stream');

// 发送流式中间内容
$wsClient->replyStream($frame, $streamId, '正在处理中...', false);

// 发送最终结果（finish=true 表示结束流）
$wsClient->replyStream($frame, $streamId, '处理完成！结果是...', true);
```

---

### `replyWelcome` 详细说明

发送欢迎语回复，需在收到 `event.enter_chat` 事件 **5 秒内**调用，超时将无法发送。

```php
// 文本欢迎语
$wsClient->replyWelcome($frame, [
    'msgtype' => 'text',
    'text' => ['content' => '欢迎！'],
]);

// 模板卡片欢迎语
$wsClient->replyWelcome($frame, [
    'msgtype' => 'template_card',
    'template_card' => ['card_type' => 'text_notice', 'main_title' => ['title' => '欢迎']],
]);
```

---

### `replyStreamWithCard` 详细说明

发送流式消息 + 模板卡片组合回复。

```php
$wsClient->replyStreamWithCard(
    frame: WsFrame,       // 收到的原始 WebSocket 帧
    streamId: string,     // 流式消息 ID
    content: string,      // 回复内容（支持 Markdown）
    finish: bool = false, // 是否结束流式消息
    options: ?array = null, // 选项（见下方）
);
```

`$options` 支持以下键：

| 键               | 类型             | 说明                                 |
| ---------------- | ---------------- | ------------------------------------ |
| `msgItem`        | `ReplyMsgItem[]` | 图文混排项（仅 finish=true 时有效）  |
| `streamFeedback` | `ReplyFeedback`  | 流式消息反馈信息（首次回复时设置）   |
| `templateCard`   | `TemplateCard`   | 模板卡片内容（同一消息只能回复一次） |
| `cardFeedback`   | `ReplyFeedback`  | 模板卡片反馈信息                     |

---

### `sendMessage` 详细说明

主动向指定会话推送消息，无需依赖收到的回调帧。

```php
// 发送 Markdown 消息
$wsClient->sendMessage('userid_or_chatid', [
    'msgtype' => 'markdown',
    'markdown' => ['content' => '这是一条**主动推送**的消息'],
]);

// 发送模板卡片消息
$wsClient->sendMessage('userid_or_chatid', [
    'msgtype' => 'template_card',
    'template_card' => ['card_type' => 'text_notice', 'main_title' => ['title' => '通知']],
]);
```

---

### `uploadMedia` 详细说明

通过 WebSocket 长连接执行三步分片上传：`init -> chunk x N -> finish`。

- 单个分片不超过 **512KB**（Base64 编码前），最多 **100 个**分片（约 50MB 上限）

```php
$filePath = '/path/to/image.png';
$result = $wsClient->uploadMedia($filePath, new UploadMediaOptions(
    type: WeComMediaType::Image,
    filename: 'image.png',
));

$result->then(function (UploadMediaFinishResult $result) use ($wsClient, $frame) {
    echo "上传成功，media_id: {$result->mediaId}\n";
    // 使用 media_id 回复图片消息
    $wsClient->replyMedia($frame, WeComMediaType::Image, $result->mediaId);
});
```

---

### `downloadFile` 详细说明

下载文件并 AES-256-CBC 解密（同步方法，内部使用 ReactPHP 事件循环）。

```php
// aesKey 取自消息体中的 image.aeskey 或 file.aeskey
$wsClient->on('message.image', function (WsFrame $frame) use ($wsClient) {
    $body = $frame->body;
    $result = $wsClient->downloadFile($body['image']['url'], $body['image']['aeskey'] ?? null);
    echo "文件名: {$result->filename}, 大小: " . strlen($result->buffer) . " bytes\n";
});
```

---

## 配置选项

`WsClientOptions` 完整配置：

| 参数                     | 类型               | 必填 | 默认值                            | 说明                                         |
| ------------------------ | ------------------ | ---- | --------------------------------- | -------------------------------------------- |
| `botId`                  | `string`           | 是   | —                                 | 机器人 ID（企业微信后台获取）                |
| `secret`                 | `string`           | 是   | —                                 | 机器人 Secret（企业微信后台获取）            |
| `scene`                  | `?int`             | —    | `null`                            | 场景值（可选）                               |
| `plugVersion`            | `?string`          | —    | `null`                            | 插件版本号（可选）                           |
| `reconnectInterval`      | `int`              | —    | `1000`                            | 重连基础延迟（毫秒），实际延迟按指数退避递增 |
| `maxReconnectAttempts`   | `int`              | —    | `10`                              | 最大重连次数（`-1` 表示无限重连）            |
| `maxAuthFailureAttempts` | `int`              | —    | `5`                               | 认证失败最大重试次数（`-1` 表示无限重试）    |
| `heartbeatInterval`      | `int`              | —    | `30000`                           | 心跳间隔（毫秒）                             |
| `requestTimeout`         | `int`              | —    | `10000`                           | HTTP 请求超时时间（毫秒）                    |
| `wsUrl`                  | `string`           | —    | `wss://openws.work.weixin.qq.com` | 自定义 WebSocket 连接地址                    |
| `maxReplyQueueSize`      | `int`              | —    | `500`                             | 单个 req_id 回复队列最大长度                 |
| `logger`                 | `?LoggerInterface` | —    | `DefaultLogger`                   | 自定义日志实例                               |

---

## 事件列表

所有事件均通过 `$wsClient->on(event, handler)` 监听（继承自 `Evenement\EventEmitter`）：

| 事件                        | 回调参数            | 说明                                         |
| --------------------------- | ------------------- | -------------------------------------------- |
| `connected`                 | —                   | WebSocket 连接建立                           |
| `authenticated`             | —                   | 认证成功                                     |
| `disconnected`              | `string $reason`    | 连接断开                                     |
| `reconnecting`              | `int $attempt`      | 正在重连（第 N 次）                          |
| `error`                     | `\Throwable $error` | 发生错误                                     |
| `message`                   | `WsFrame $frame`    | 收到消息（所有类型）                         |
| `message.text`              | `WsFrame $frame`    | 收到文本消息                                 |
| `message.image`             | `WsFrame $frame`    | 收到图片消息                                 |
| `message.mixed`             | `WsFrame $frame`    | 收到图文混排消息                             |
| `message.voice`             | `WsFrame $frame`    | 收到语音消息                                 |
| `message.file`              | `WsFrame $frame`    | 收到文件消息                                 |
| `message.video`             | `WsFrame $frame`    | 收到视频消息                                 |
| `event`                     | `WsFrame $frame`    | 收到事件回调（所有事件类型）                 |
| `event.enter_chat`          | `WsFrame $frame`    | 收到进入会话事件（用户当天首次进入单聊会话） |
| `event.template_card_event` | `WsFrame $frame`    | 收到模板卡片事件（用户点击卡片按钮）         |
| `event.feedback_event`      | `WsFrame $frame`    | 收到用户反馈事件                             |

---

## 消息类型

SDK 支持以下消息类型（`MessageType` 枚举）：

| 类型    | 值        | 说明                                                     |
| ------- | --------- | -------------------------------------------------------- |
| `Text`  | `'text'`  | 文本消息                                                 |
| `Image` | `'image'` | 图片消息（URL 已加密，使用消息中的 `image.aeskey` 解密） |
| `Mixed` | `'mixed'` | 图文混排消息（包含 text / image 子项）                   |
| `Voice` | `'voice'` | 语音消息（已转文本）                                     |
| `File`  | `'file'`  | 文件消息（URL 已加密，使用消息中的 `file.aeskey` 解密）  |
| `Video` | `'video'` | 视频消息（URL 已加密，使用消息中的 `video.aeskey` 解密） |

SDK 支持以下事件类型（`EventType` 枚举）：

| 类型                | 值                      | 说明                           |
| ------------------- | ----------------------- | ------------------------------ |
| `EnterChat`         | `'enter_chat'`          | 用户当天首次进入机器人单聊会话 |
| `TemplateCardEvent` | `'template_card_event'` | 用户点击模板卡片按钮           |
| `FeedbackEvent`     | `'feedback_event'`      | 用户对机器人回复进行反馈       |

SDK 支持以下媒体类型（`WeComMediaType` 枚举）：

| 类型    | 值        |
| ------- | --------- |
| `File`  | `'file'`  |
| `Image` | `'image'` |
| `Voice` | `'voice'` |
| `Video` | `'video'` |

---

## 模板卡片类型

SDK 支持以下模板卡片类型（`TemplateCardType` 枚举）：

| 类型                  | 值                       | 说明             |
| --------------------- | ------------------------ | ---------------- |
| `TextNotice`          | `'text_notice'`          | 文本通知模版卡片 |
| `NewsNotice`          | `'news_notice'`          | 图文展示模版卡片 |
| `ButtonInteraction`   | `'button_interaction'`   | 按钮交互模版卡片 |
| `VoteInteraction`     | `'vote_interaction'`     | 投票选择模版卡片 |
| `MultipleInteraction` | `'multiple_interaction'` | 多项选择模版卡片 |

---

## 消息帧结构

### `WsFrame`

```php
readonly class WsFrame
{
    public function __construct(
        public ?string $cmd,          // 命令类型
        public WsFrameHeaders $headers, // headers 含 req_id（回复时需透传）
        public ?array $body = null,   // 消息体
        public ?int $errcode = null,  // 响应错误码
        public ?string $errmsg = null, // 响应错误信息
    ) {}
}
```

---

## 自定义日志

实现 `LoggerInterface` 即可自定义日志输出：

```php
interface LoggerInterface
{
    public function debug(string $message, mixed ...$args): void;
    public function info(string $message, mixed ...$args): void;
    public function warn(string $message, mixed ...$args): void;
    public function error(string $message, mixed ...$args): void;
}
```

使用示例：

```php
$wsClient = new WSClient(new WsClientOptions(
    botId: 'your-bot-id',
    secret: 'your-bot-secret',
    logger: new class implements \VergilLai\WecomAiBot\LoggerInterface {
        public function debug(string $msg, mixed ...$args): void {} // 静默 debug
        public function info(string $msg, mixed ...$args): void { echo "[INFO] {$msg}\n"; }
        public function warn(string $msg, mixed ...$args): void { echo "[WARN] {$msg}\n"; }
        public function error(string $msg, mixed ...$args): void { echo "[ERROR] {$msg}\n"; }
    },
));
```

---

## WebSocket 命令协议

| 方向           | 常量                  | 值                          | 说明              |
| -------------- | --------------------- | --------------------------- | ----------------- |
| 开发者 -> 企微 | `SUBSCRIBE`           | `aibot_subscribe`           | 认证订阅          |
| 开发者 -> 企微 | `HEARTBEAT`           | `ping`                      | 心跳              |
| 开发者 -> 企微 | `RESPONSE`            | `aibot_respond_msg`         | 回复消息          |
| 开发者 -> 企微 | `RESPONSE_WELCOME`    | `aibot_respond_welcome_msg` | 回复欢迎语        |
| 开发者 -> 企微 | `RESPONSE_UPDATE`     | `aibot_respond_update_msg`  | 更新模板卡片      |
| 开发者 -> 企微 | `SEND_MSG`            | `aibot_send_msg`            | 主动发送消息      |
| 开发者 -> 企微 | `UPLOAD_MEDIA_INIT`   | `aibot_upload_media_init`   | 上传素材 - 初始化 |
| 开发者 -> 企微 | `UPLOAD_MEDIA_CHUNK`  | `aibot_upload_media_chunk`  | 上传素材 - 分片   |
| 开发者 -> 企微 | `UPLOAD_MEDIA_FINISH` | `aibot_upload_media_finish` | 上传素材 - 完成   |
| 企微 -> 开发者 | `CALLBACK`            | `aibot_msg_callback`        | 消息推送回调      |
| 企微 -> 开发者 | `EVENT_CALLBACK`      | `aibot_event_callback`      | 事件推送回调      |

---

## 项目结构

```
aibot-php-sdk/
├── src/
│   ├── WSClient.php              # WSClient 核心客户端（继承 EventEmitter）
│   ├── WsConnectionManager.php   # WebSocket 长连接管理器
│   ├── MessageHandler.php        # 消息解析与事件分发
│   ├── WeComApiClient.php        # HTTP API 客户端（文件下载）
│   ├── Crypto.php                # AES-256-CBC 文件解密
│   ├── DefaultLogger.php         # 默认日志实现
│   ├── LoggerInterface.php       # 日志接口
│   ├── Utils.php                 # 工具方法（generateReqId 等）
│   ├── Exceptions/
│   │   ├── WSAuthFailureError.php
│   │   └── WSReconnectExhaustedError.php
│   └── Types/
│       ├── WsFrame.php           # 帧结构
│       ├── WsFrameHeaders.php
│       ├── WsCmd.php             # 命令枚举
│       ├── WsClientOptions.php   # 配置选项
│       ├── MessageType.php       # 消息类型枚举
│       ├── EventType.php         # 事件类型枚举
│       ├── WeComMediaType.php    # 媒体类型枚举
│       ├── TemplateCardType.php  # 模板卡片类型枚举
│       ├── TemplateCard.php
│       ├── ReplyMsgItem.php
│       ├── ReplyFeedback.php
│       ├── UploadMediaOptions.php
│       ├── UploadMediaFinishResult.php
│       ├── DownloadFileResult.php
│       └── ...                   # 其他消息/事件类型
├── examples/
│   └── basic.php                 # 基础使用示例
├── composer.json
└── README.md
```

---

## 完整使用示例

### 流式回复

```php
$wsClient->on('message.text', function (WsFrame $frame) use ($wsClient) {
    $streamId = Utils::generateReqId('stream');

    // 流式中间内容
    $wsClient->replyStream($frame, $streamId, '正在生成内容...', false);

    // 流式结束
    React\EventLoop\Loop::addTimer(1.0, function () use ($wsClient, $frame, $streamId) {
        $wsClient->replyStream($frame, $streamId, '这是最终结果', true);
    });
});
```

### 上传素材 + 回复媒体消息

```php
$wsClient->on('message.text', function (WsFrame $frame) use ($wsClient) {
    $filePath = '/path/to/document.pdf';

    $wsClient->uploadMedia($filePath, new UploadMediaOptions(
        type: WeComMediaType::File,
        filename: 'document.pdf',
    ))->then(function (UploadMediaFinishResult $result) use ($wsClient, $frame) {
        $wsClient->replyMedia($frame, WeComMediaType::File, $result->mediaId);
    });
});
```

### 主动推送消息

```php
$wsClient->on('authenticated', function () use ($wsClient) {
    // 向指定用户推送 Markdown 消息
    $wsClient->sendMessage('target_userid', [
        'msgtype' => 'markdown',
        'markdown' => ['content' => '# 通知\n\n这是一条**主动推送**的消息。'],
    ]);
});
```

### 模板卡片交互

```php
// 回复带按钮的模板卡片
$wsClient->on('message.text', function (WsFrame $frame) use ($wsClient) {
    $wsClient->replyTemplateCard($frame, new TemplateCard(
        cardType: TemplateCardType::ButtonInteraction,
        mainTitle: ['title' => '请选择操作', 'desc' => '点击下方按钮进行操作'],
        buttonList: [
            ['text' => '确认', 'key' => 'btn_confirm', 'style' => 1],
            ['text' => '取消', 'key' => 'btn_cancel', 'style' => 2],
        ],
        taskId: 'task_' . time(),
    ));
});

// 监听卡片按钮点击事件并更新卡片
$wsClient->on('event.template_card_event', function (WsFrame $frame) use ($wsClient) {
    $eventKey = $frame->body['event']['event_key'] ?? '';
    $taskId = $frame->body['event']['task_id'] ?? '';

    $wsClient->updateTemplateCard($frame, new TemplateCard(
        cardType: TemplateCardType::TextNotice,
        mainTitle: ['title' => $eventKey === 'btn_confirm' ? '已确认' : '已取消'],
        taskId: $taskId,
    ));
});
```

### 文件下载解密

```php
$wsClient->on('message.image', function (WsFrame $frame) use ($wsClient) {
    $body = $frame->body;
    $imageUrl = $body['image']['url'] ?? '';
    if (empty($imageUrl)) return;

    $result = $wsClient->downloadFile($imageUrl, $body['image']['aeskey'] ?? null);
    $fileName = $result->filename ?? 'image_' . time() . '.jpg';
    file_put_contents(__DIR__ . '/' . $fileName, $result->buffer);
    echo "图片已保存: {$fileName} (" . strlen($result->buffer) . " bytes)\n";
});
```

---

## License

MIT
