<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 文件下载结果
 */
readonly class DownloadFileResult
{
    public function __construct(
        public string $buffer,
        public ?string $filename = null,
    ) {}
}
