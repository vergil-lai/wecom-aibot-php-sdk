<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 图文混排项
 */
class MixedItem
{
    public function __construct(
        public string $msgType,
        public ?TextContent $text = null,
        public ?ImageContent $image = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $msgType = $data['msgtype'] ?? 'text';
        $text = null;
        $image = null;

        if ($msgType === 'text' && isset($data['text'])) {
            $text = TextContent::fromArray($data['text']);
        } elseif ($msgType === 'image' && isset($data['image'])) {
            $image = ImageContent::fromArray($data['image']);
        }

        return new self(
            msgType: $msgType,
            text: $text,
            image: $image,
        );
    }
}
