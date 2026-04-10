<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 事件消息
 */
class EventMessage
{
    public function __construct(
        public string $msgId,
        public int $createTime,
        public string $aibotId,
        public ?string $chatId = null,
        public ?string $chatType = null,
        public ?EventFrom $from = null,
        public ?EventContent $event = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $from = null;
        if (isset($data['from'])) {
            $from = EventFrom::fromArray($data['from']);
        }

        $event = null;
        if (isset($data['event'])) {
            $event = EventContent::fromArray($data['event']);
        }

        return new self(
            msgId: $data['msgid'] ?? '',
            createTime: $data['create_time'] ?? 0,
            aibotId: $data['aibotid'] ?? '',
            chatId: $data['chatid'] ?? null,
            chatType: $data['chattype'] ?? null,
            from: $from,
            event: $event,
        );
    }
}
