<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 引用消息内容
 */
final class QuoteContent
{
    public function __construct(
        public string $msgId,
        public string $content,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            msgId: $data['msgid'] ?? '',
            content: $data['content'] ?? '',
        );
    }
}
