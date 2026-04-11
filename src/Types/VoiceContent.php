<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 语音内容
 */
final class VoiceContent
{
    public function __construct(
        public string $mediaId,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            mediaId: $data['media_id'] ?? '',
        );
    }
}
