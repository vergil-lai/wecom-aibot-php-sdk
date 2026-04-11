<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 文本内容
 */
final class TextContent
{
    public function __construct(
        public string $content,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            content: $data['content'] ?? '',
        );
    }
}
