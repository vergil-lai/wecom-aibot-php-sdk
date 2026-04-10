<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

use Evenement\EventEmitterInterface;
use VergilLai\WecomAiBot\Types\WsFrame;
use VergilLai\WecomAiBot\Types\WsCmd;

/**
 * 消息解析与事件分发
 */
class MessageHandler
{
    private EventEmitterInterface $emitter;
    private LoggerInterface $logger;

    public function __construct(
        EventEmitterInterface $emitter,
        LoggerInterface $logger,
    ) {
        $this->emitter = $emitter;
        $this->logger = $logger;
    }

    /**
     * 解析 JSON 为 WsFrame
     */
    public function parseFrame(string $data): ?WsFrame
    {
        try {
            $arr = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            return \VergilLai\WecomAiBot\Types\WsFrame::fromArray($arr);
        } catch (\JsonException $e) {
            $this->logger->error('JSON parse error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 分发帧到对应事件
     */
    public function dispatch(WsFrame $frame): void
    {
        $cmd = $frame->cmd ?? '';

        $this->logger->debug("Dispatching cmd: {$cmd}");

        match ($cmd) {
            WsCmd::CALLBACK->value => $this->handleMessageCallback($frame),
            WsCmd::EVENT_CALLBACK->value => $this->handleEventCallback($frame),
            default => $this->logger->warn("Unknown cmd: {$cmd}"),
        };
    }

    /**
     * 处理消息回调
     */
    private function handleMessageCallback(WsFrame $frame): void
    {
        $body = $frame->body;
        if ($body === null) {
            return;
        }

        $msgType = $body['msgtype'] ?? 'unknown';

        // 触发通用 message 事件
        $this->emitter->emit('message', [$frame]);

        // 触发具体类型的消息事件
        $this->emitter->emit('message.' . $msgType, [$frame]);

        $this->logger->debug("Message type: {$msgType}");
    }

    /**
     * 处理事件回调
     */
    private function handleEventCallback(WsFrame $frame): void
    {
        $body = $frame->body;
        if ($body === null) {
            return;
        }

        $eventData = $body['event'] ?? [];
        $eventType = $eventData['eventtype'] ?? 'unknown';

        // 触发通用 event 事件
        $this->emitter->emit('event', [$frame]);

        // 触发具体类型的事件
        $this->emitter->emit('event.' . $eventType, [$frame]);

        $this->logger->debug("Event type: {$eventType}");
    }
}
