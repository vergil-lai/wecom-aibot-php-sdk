<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 消息类型
 */
enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Mixed = 'mixed';
    case Voice = 'voice';
    case File = 'file';
    case Video = 'video';
}
