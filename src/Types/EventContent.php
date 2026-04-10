<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 事件内容
 */
class EventContent
{
    public function __construct(
        public EventType $eventType,
        public ?string $eventKey = null,
        public ?string $taskId = null,
        public ?string $responseType = null,
        public ?string $feedbackId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $eventTypeStr = $data['eventtype'] ?? '';
        $eventType = EventType::tryFrom($eventTypeStr) ?? EventType::EnterChat;

        return new self(
            eventType: $eventType,
            eventKey: $data['event_key'] ?? null,
            taskId: $data['task_id'] ?? null,
            responseType: $data['response_type'] ?? null,
            feedbackId: $data['feedback_id'] ?? null,
        );
    }
}
