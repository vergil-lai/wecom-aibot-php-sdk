<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 基础消息结构
 */
class BaseMessage
{
    public function __construct(
        public string $msgId,
        public string $aibotId,
        public string $chatType,
        public ?string $chatId = null,
        public ?FromInfo $from = null,
        public ?int $createTime = null,
        public ?string $responseUrl = null,
        public ?string $msgType = null,
        public ?QuoteContent $quote = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $from = null;
        if (isset($data['from'])) {
            $from = FromInfo::fromArray($data['from']);
        }

        $quote = null;
        if (isset($data['quote'])) {
            $quote = QuoteContent::fromArray($data['quote']);
        }

        return new self(
            msgId: $data['msgid'] ?? '',
            aibotId: $data['aibotid'] ?? '',
            chatId: $data['chatid'] ?? null,
            chatType: $data['chattype'] ?? 'single',
            from: $from,
            createTime: $data['create_time'] ?? null,
            responseUrl: $data['response_url'] ?? null,
            msgType: $data['msgtype'] ?? null,
            quote: $quote,
        );
    }
}
