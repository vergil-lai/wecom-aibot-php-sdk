<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 流式回复 body
 */
class StreamReplyBody
{
    public function __construct(
        public string $streamId,
        public bool $finish = false,
        public string $content = '',
        public array $msgItem = [],
        public array $feedback = [],
    ) {}

    public function toArray(): array
    {
        $result = [
            'msgtype' => 'stream',
            'stream' => [
                'id' => $this->streamId,
                'finish' => $this->finish,
                'content' => $this->content,
            ],
        ];

        if (!empty($this->msgItem) && $this->finish) {
            $result['stream']['msg_item'] = $this->msgItem;
        }

        if (!empty($this->feedback)) {
            $result['stream']['feedback'] = $this->feedback;
        }

        return $result;
    }
}
