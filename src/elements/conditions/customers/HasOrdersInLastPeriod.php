<?php

namespace craft\commerce\elements\conditions\customers;

use Craft;
use craft\base\conditions\BaseDateRangeConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Html;
use yii\base\NotSupportedException;

class HasOrdersInLastPeriod extends BaseDateRangeConditionRule implements ElementConditionRuleInterface
{
    public string $numberOfDays = '';

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Has Orders in Last Period');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['hasOrdersInLastPeriod'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Days since last purchase condition rule does not support queries');
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return array_merge(parent::getConfig(), [
            'numberOfDays' => $this->numberOfDays,
        ]);
    }

    /**
     * @return string|null
     */
    public function getStartDate(): ?string
    {
        return date('Y-m-d', strtotime('-' . $this->numberOfDays . ' days'));
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['numberOfDays'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return Order::find()
            ->customerId($element->id)
            ->isCompleted(true)
            ->dateOrdered($this->queryParamValue())
            ->exists();
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(): string
    {
        return Html::beginTag('div', ['class' => 'flex']) .
            Html::label(Craft::t('commerce', 'In last number of days'), 'numberOfDays') .
            Cp::textHtml([
                'type' => 'number',
                'id' => 'numberOfDays',
                'name' => 'numberOfDays',
                'value' => $this->numberOfDays,
                'autocomplete' => false,
                'class' => 'flex-grow flex-shrink',
            ]) .
            Html::endTag('div');
    }
}
