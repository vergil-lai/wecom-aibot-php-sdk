<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 反馈信息
 */
class ReplyFeedback
{
    public function __construct(
        public string $id,
    ) {}

    public function toArray(): array
    {
        return ['id' => $this->id];
    }
}
