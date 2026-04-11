<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 语音消息
 */
final class VoiceMessage extends BaseMessage
{
    public function __construct(
        string $msgId,
        string $aibotId,
        string $chatType,
        public VoiceContent $voice,
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
            msgType: $msgType ?? 'voice',
            quote: $quote,
        );
    }

    public static function fromArray(array $data): static
    {
        $voice = VoiceContent::fromArray($data['voice'] ?? []);
        $base = BaseMessage::fromArray($data);

        return new static(
            msgId: $base->msgId,
            aibotId: $base->aibotId,
            chatType: $base->chatType,
            voice: $voice,
            chatId: $base->chatId,
            from: $base->from,
            createTime: $base->createTime,
            responseUrl: $base->responseUrl,
            msgType: $base->msgType,
            quote: $base->quote,
        );
    }
}
