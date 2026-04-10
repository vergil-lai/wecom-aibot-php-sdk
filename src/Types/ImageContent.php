<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 图片内容
 */
class ImageContent
{
    public function __construct(
        public string $url,
        public string $aesKey,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            aesKey: $data['aeskey'] ?? '',
        );
    }
}
