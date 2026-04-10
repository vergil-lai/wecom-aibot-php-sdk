<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * WebSocket 帧头
 */
readonly class WsFrameHeaders
{
    public function __construct(
        public string $reqId,
        public array $extra = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $reqId = $data['req_id'] ?? '';
        $extra = array_diff_key($data, ['req_id' => true]);
        return new self($reqId, $extra);
    }

    public function toArray(): array
    {
        return array_merge(['req_id' => $this->reqId], $this->extra);
    }
}
