<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 发送者信息
 */
class FromInfo
{
    public function __construct(
        public string $userId,
        public ?string $corpid = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['userid'] ?? '',
            corpid: $data['corpid'] ?? null,
        );
    }
}
