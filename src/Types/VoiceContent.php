<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 语音内容
 */
class VoiceContent
{
    public function __construct(
        public string $mediaId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            mediaId: $data['media_id'] ?? '',
        );
    }
}
