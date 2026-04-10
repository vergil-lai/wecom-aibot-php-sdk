<?php

declare(strict_types=1);

namespace VergilLai\WecomAiBot\Types;

/**
 * 模板卡片结构
 */
class TemplateCard
{
    public function __construct(
        public TemplateCardType $cardType,
        // Common fields
        public array $source = [],
        public array $actionMenu = [],
        public array $mainTitle = [],
        public array $emphasisContent = [],
        public array $quoteArea = [],
        public string $subTitleText = '',
        public array $horizontalContentList = [],
        public array $jumpList = [],
        public array $cardAction = [],
        // NewsNotice specific
        public array $cardImage = [],
        public array $imageTextArea = [],
        public array $verticalContentList = [],
        // ButtonInteraction specific
        public array $buttonSelection = [],
        public array $buttonList = [],
        // VoteInteraction specific
        public array $checkbox = [],
        // MultipleInteraction specific
        public array $selectList = [],
        public array $submitButton = [],
        // Common fields (taskId, feedback)
        public ?string $taskId = null,
        public array $feedback = [],
    ) {}

    /**
     * 从企业微信 API 的 JSON 格式数组创建 TemplateCard 实例
     * 自动将蛇形命名转换为驼峰命名
     */
    public static function fromApiArray(array $data): self
    {
        return new self(
            cardType: TemplateCardType::from($data['card_type'] ?? 'text_notice'),
            source: $data['source'] ?? [],
            actionMenu: $data['action_menu'] ?? [],
            mainTitle: $data['main_title'] ?? [],
            emphasisContent: $data['emphasis_content'] ?? [],
            quoteArea: $data['quote_area'] ?? [],
            subTitleText: $data['sub_title_text'] ?? '',
            horizontalContentList: $data['horizontal_content_list'] ?? [],
            jumpList: $data['jump_list'] ?? [],
            cardAction: $data['card_action'] ?? [],
            cardImage: $data['card_image'] ?? [],
            imageTextArea: $data['image_text_area'] ?? [],
            verticalContentList: $data['vertical_content_list'] ?? [],
            buttonSelection: $data['button_selection'] ?? [],
            buttonList: $data['button_list'] ?? [],
            checkbox: $data['checkbox'] ?? [],
            selectList: $data['select_list'] ?? [],
            submitButton: $data['submit_button'] ?? [],
            taskId: $data['task_id'] ?? null,
            feedback: $data['feedback'] ?? [],
        );
    }

    public function toArray(): array
    {
        $result = [
            'card_type' => $this->cardType->value,
        ];

        if (! empty($this->source)) {
            $result['source'] = $this->source;
        }

        if (! empty($this->actionMenu)) {
            $result['action_menu'] = $this->actionMenu;
        }

        if (! empty($this->mainTitle)) {
            $result['main_title'] = $this->mainTitle;
        }

        if (! empty($this->emphasisContent)) {
            $result['emphasis_content'] = $this->emphasisContent;
        }

        if (! empty($this->quoteArea)) {
            $result['quote_area'] = $this->quoteArea;
        }

        if ($this->subTitleText !== '') {
            $result['sub_title_text'] = $this->subTitleText;
        }

        if (! empty($this->horizontalContentList)) {
            $result['horizontal_content_list'] = $this->horizontalContentList;
        }

        if (! empty($this->jumpList)) {
            $result['jump_list'] = $this->jumpList;
        }

        if (! empty($this->cardAction)) {
            $result['card_action'] = $this->cardAction;
        }

        if (! empty($this->cardImage)) {
            $result['card_image'] = $this->cardImage;
        }

        if (! empty($this->imageTextArea)) {
            $result['image_text_area'] = $this->imageTextArea;
        }

        if (! empty($this->verticalContentList)) {
            $result['vertical_content_list'] = $this->verticalContentList;
        }

        if (! empty($this->buttonSelection)) {
            $result['button_selection'] = $this->buttonSelection;
        }

        if (! empty($this->buttonList)) {
            $result['button_list'] = $this->buttonList;
        }

        if (! empty($this->checkbox)) {
            $result['checkbox'] = $this->checkbox;
        }

        if (! empty($this->selectList)) {
            $result['select_list'] = $this->selectList;
        }

        if (! empty($this->submitButton)) {
            $result['submit_button'] = $this->submitButton;
        }

        if ($this->taskId !== null) {
            $result['task_id'] = $this->taskId;
        }

        if (! empty($this->feedback)) {
            $result['feedback'] = $this->feedback;
        }

        return $result;
    }
}
