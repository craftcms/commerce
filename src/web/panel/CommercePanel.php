<?php


namespace craft\commerce\web\panel;


use yii\debug\Panel;

class CommercePanel extends Panel
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Commerce';
    }

    /**
     * @inheritDoc
     */
    public function getSummary(): string
    {
        // TODO Add a real panel COM-56
        return '<div class="yii-debug-toolbar__block"><a href="#47" title="">Cart <span class="yii-debug-toolbar__label yii-debug-toolbar__label_success">3</span></a></div>';
    }

    /**
     * @inheritDoc
     */
    public function getDetail(): string
    {
        return 'Detail data';
    }
}