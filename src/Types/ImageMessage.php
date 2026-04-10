<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 图片消息
 */
class ImageMessage extends BaseMessage
{
    public function __construct(
        string $msgId,
        string $aibotId,
        string $chatType,
        public ImageContent $image,
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
            msgType: $msgType ?? 'image',
            quote: $quote,
        );
    }

    public static function fromArray(array $data): self
    {
        $image = ImageContent::fromArray($data['image'] ?? []);
        $base = BaseMessage::fromArray($data);

        return new self(
            msgId: $base->msgId,
            aibotId: $base->aibotId,
            chatType: $base->chatType,
            image: $image,
            chatId: $base->chatId,
            from: $base->from,
            createTime: $base->createTime,
            responseUrl: $base->responseUrl,
            msgType: $base->msgType,
            quote: $base->quote,
        );
    }
}
