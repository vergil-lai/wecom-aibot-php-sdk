<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 视频内容
 */
final class VideoContent
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
