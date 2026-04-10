<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 文本内容
 */
class TextContent
{
    public function __construct(
        public string $content,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? '',
        );
    }
}
