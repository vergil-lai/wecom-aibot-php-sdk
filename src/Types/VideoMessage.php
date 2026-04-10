<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 视频消息
 */
class VideoMessage extends BaseMessage
{
    public function __construct(
        string $msgId,
        string $aibotId,
        string $chatType,
        public VideoContent $video,
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
            msgType: $msgType ?? 'video',
            quote: $quote,
        );
    }

    public static function fromArray(array $data): self
    {
        $video = VideoContent::fromArray($data['video'] ?? []);
        $base = BaseMessage::fromArray($data);

        return new self(
            msgId: $base->msgId,
            aibotId: $base->aibotId,
            chatType: $base->chatType,
            video: $video,
            chatId: $base->chatId,
            from: $base->from,
            createTime: $base->createTime,
            responseUrl: $base->responseUrl,
            msgType: $base->msgType,
            quote: $base->quote,
        );
    }
}
