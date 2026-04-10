<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 媒体类型
 */
enum WeComMediaType: string
{
    case File = 'file';
    case Image = 'image';
    case Voice = 'voice';
    case Video = 'video';
}
