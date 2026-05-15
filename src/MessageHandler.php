<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot;

use Evenement\EventEmitterInterface;
use Psr\Log\LoggerInterface;
use VergilLai\WecomAiBot\Types\WsFrame;
use VergilLai\WecomAiBot\Types\WsCmd;
use VergilLai\WecomAiBot\Types\MessageType;

/**
 * 消息处理器
 * 负责解析 WebSocket 帧并分发为具体的消息事件和事件回调
 */
class MessageHandler
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
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
     * 处理收到的 WebSocket 帧，解析并触发对应的消息/事件
     *
     * @param WsFrame $frame WebSocket 接收帧
     * @param EventEmitterInterface $emitter 用于触发事件的 emitter
     */
    public function handleFrame(WsFrame $frame, EventEmitterInterface $emitter): void
    {
        try {
            $body = $frame->body;

            if ($body === null || !isset($body['msgtype'])) {
                $this->logger->warning('Received invalid message format: ' . json_encode($frame));
                return;
            }

            // 事件推送回调处理
            if ($frame->cmd === WsCmd::EVENT_CALLBACK->value) {
                $this->handleEventCallback($frame, $emitter);
                return;
            }

            // 消息推送回调处理
            $this->handleMessageCallback($frame, $emitter);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to handle message: ' . $e->getMessage());
        }
    }

    /**
     * 处理消息推送回调 (aibot_msg_callback)
     */
    private function handleMessageCallback(WsFrame $frame, EventEmitterInterface $emitter): void
    {
        $body = $frame->body;
        $msgType = $body['msgtype'] ?? '';

        // 触发通用 message 事件
        $emitter->emit('message', [$frame]);

        // 根据 body 中的消息类型触发特定事件
        match ($msgType) {
            MessageType::Text->value => $emitter->emit('message.text', [$frame]),
            MessageType::Image->value => $emitter->emit('message.image', [$frame]),
            MessageType::Mixed->value => $emitter->emit('message.mixed', [$frame]),
            MessageType::Voice->value => $emitter->emit('message.voice', [$frame]),
            MessageType::File->value => $emitter->emit('message.file', [$frame]),
            MessageType::Video->value => $emitter->emit('message.video', [$frame]),
            default => $this->logger->debug("Received unhandled message type: {$msgType}"),
        };
    }

    /**
     * 处理事件推送回调 (aibot_event_callback)
     */
    private function handleEventCallback(WsFrame $frame, EventEmitterInterface $emitter): void
    {
        $body = $frame->body;
        $eventData = $body['event'] ?? [];
        $eventType = $eventData['eventtype'] ?? null;

        // 触发通用 event 事件
        $emitter->emit('event', [$frame]);

        // 根据事件类型触发特定事件
        if ($eventType !== null) {
            $emitter->emit('event.' . $eventType, [$frame]);
        } else {
            $this->logger->debug('Received event callback without eventtype: ' . json_encode($body));
        }
    }
}
