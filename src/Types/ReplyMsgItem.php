<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 图文混排项
 */
class ReplyMsgItem
{
    public function __construct(
        public string $msgType,
        public array $content,
    ) {}

    public function toArray(): array
    {
        return array_merge(['msgtype' => $this->msgType], $this->content);
    }
}
