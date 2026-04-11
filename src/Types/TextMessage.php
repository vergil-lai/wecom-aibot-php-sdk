<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 文本消息
 */
final class TextMessage extends BaseMessage
{
    public function __construct(
        string $msgId,
        string $aibotId,
        string $chatType,
        public TextContent $text,
        ?string $chatId = null,
        ?FromInfo $from = null,
        ?int $createTime = null,
        ?string $responseUrl = null,
        ?string $msgType = null,
        ?QuoteContent $quote = null,
    ) {
        parent::__construct(
            msgId: $msgId,
            aibotId: $aibotId,
            chatType: $chatType,
            chatId: $chatId,
            from: $from,
            createTime: $createTime,
            responseUrl: $responseUrl,
            msgType: $msgType ?? 'text',
            quote: $quote,
        );
    }

    public static function fromArray(array $data): static
    {
        $text = TextContent::fromArray($data['text'] ?? []);
        $base = BaseMessage::fromArray($data);

        return new static(
            msgId: $base->msgId,
            aibotId: $base->aibotId,
            chatType: $base->chatType,
            text: $text,
            chatId: $base->chatId,
            from: $base->from,
            createTime: $base->createTime,
            responseUrl: $base->responseUrl,
            msgType: $base->msgType,
            quote: $base->quote,
        );
    }
}
