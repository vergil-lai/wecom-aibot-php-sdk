<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 事件类型
 */
enum EventType: string
{
    case EnterChat = 'enter_chat';
    case TemplateCardEvent = 'template_card_event';
    case FeedbackEvent = 'feedback_event';
    case DisconnectedEvent = 'disconnected_event';
}
