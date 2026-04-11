<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 事件类型
 * @see https://developer.work.weixin.qq.com/document/path/101027
 */
enum EventType: string
{
    // 进入会话事件
    case EnterChat = 'enter_chat';

    // 模板卡片事件
    case TemplateCardEvent = 'template_card_event';

    // 用户反馈事件
    case FeedbackEvent = 'feedback_event';

    // 连接断开事件
    case DisconnectedEvent = 'disconnected_event';
}
