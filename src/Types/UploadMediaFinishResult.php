<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 上传素材结果
 */
readonly class UploadMediaFinishResult
{
    public function __construct(
        public WeComMediaType $type,
        public string $mediaId,
        public int $createdAt,
    ) {}
}
