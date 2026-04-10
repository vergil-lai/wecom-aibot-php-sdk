<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * WebSocket 帧结构
 */
readonly class WsFrame
{
    public function __construct(
        public ?string $cmd,
        public WsFrameHeaders $headers,
        public ?array $body = null,
        public ?int $errcode = null,
        public ?string $errmsg = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $headers = WsFrameHeaders::fromArray($data['headers'] ?? []);
        return new self(
            cmd: $data['cmd'] ?? null,
            headers: $headers,
            body: $data['body'] ?? null,
            errcode: $data['errcode'] ?? null,
            errmsg: $data['errmsg'] ?? null,
        );
    }

    public function toArray(): array
    {
        $result = [
            'headers' => $this->headers->toArray(),
        ];
        if ($this->cmd !== null) {
            $result['cmd'] = $this->cmd;
        }
        if ($this->body !== null) {
            $result['body'] = $this->body;
        }
        if ($this->errcode !== null) {
            $result['errcode'] = $this->errcode;
        }
        if ($this->errmsg !== null) {
            $result['errmsg'] = $this->errmsg;
        }
        return $result;
    }

    public function isSuccess(): bool
    {
        return $this->errcode === 0 || $this->errcode === null;
    }
}
