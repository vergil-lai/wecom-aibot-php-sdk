<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 上传素材选项
 */
readonly class UploadMediaOptions
{
    public function __construct(
        public WeComMediaType $type,
        public string $filename,
    ) {}
}
