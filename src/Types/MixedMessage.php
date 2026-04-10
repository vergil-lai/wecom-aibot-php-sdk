<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 图文混排消息
 */
class MixedMessage extends BaseMessage
{
    /** @var array<MixedItem> */
    public array $mixedList = [];

    public function __construct(
        string $msgId,
        string $aibotId,
        string $chatType,
        array $mixedList = [],
        ?string $chatId = null,
        ?FromInfo $from = null,
        ?int $createTime = null,
        ?string $responseUrl = null,
        ?string $msgType = null,
        ?QuoteContent $quote = null,
    ) {
        $this->mixedList = $mixedList;
        parent::__construct(
            msgId: $msgId,
            aibotId: $aibotId,
            chatType: $chatType,
            chatId: $chatId,
            from: $from,
            createTime: $createTime,
            responseUrl: $responseUrl,
            msgType: $msgType ?? 'mixed',
            quote: $quote,
        );
    }

    public static function fromArray(array $data): self
    {
        $mixedList = [];
        foreach ($data['mixed'] ?? [] as $item) {
            $mixedList[] = MixedItem::fromArray($item);
        }
        $base = BaseMessage::fromArray($data);

        return new self(
            msgId: $base->msgId,
            aibotId: $base->aibotId,
            chatType: $base->chatType,
            mixedList: $mixedList,
            chatId: $base->chatId,
            from: $base->from,
            createTime: $base->createTime,
            responseUrl: $base->responseUrl,
            msgType: $base->msgType,
            quote: $base->quote,
        );
    }
}
