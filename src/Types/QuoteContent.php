<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 引用消息内容
 */
class QuoteContent
{
    public function __construct(
        public string $msgId,
        public string $content,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            msgId: $data['msgid'] ?? '',
            content: $data['content'] ?? '',
        );
    }
}
