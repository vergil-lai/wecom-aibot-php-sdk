<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 模板卡片类型
 */
enum TemplateCardType: string
{
    case TextNotice = 'text_notice';
    case NewsNotice = 'news_notice';
    case ButtonInteraction = 'button_interaction';
    case VoteInteraction = 'vote_interaction';
    case MultipleInteraction = 'multiple_interaction';
}
