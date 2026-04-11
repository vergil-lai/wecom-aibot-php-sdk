<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 事件内容
 */
final class EventContent
{
    public function __construct(
        public EventType $eventType,
        public ?string $eventKey = null,
        public ?string $taskId = null,
        public ?string $responseType = null,
        public ?string $feedbackId = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $eventTypeStr = $data['eventtype'] ?? '';
        $eventType = EventType::tryFrom($eventTypeStr) ?? EventType::EnterChat;

        return new static(
            eventType: $eventType,
            eventKey: $data['event_key'] ?? null,
            taskId: $data['task_id'] ?? null,
            responseType: $data['response_type'] ?? null,
            feedbackId: $data['feedback_id'] ?? null,
        );
    }
}
