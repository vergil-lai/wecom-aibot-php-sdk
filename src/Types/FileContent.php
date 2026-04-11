<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 文件内容
 */
final class FileContent
{
    public function __construct(
        public string $url,
        public string $aesKey,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            url: $data['url'] ?? '',
            aesKey: $data['aeskey'] ?? '',
        );
    }
}
