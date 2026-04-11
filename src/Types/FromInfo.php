<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 发送者信息
 */
final class FromInfo
{
    public function __construct(
        public string $userId,
        public ?string $corpid = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            userId: $data['userid'] ?? '',
            corpid: $data['corpid'] ?? null,
        );
    }
}
