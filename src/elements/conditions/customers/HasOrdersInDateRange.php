<?php

namespace craft\commerce\elements\conditions\customers;

use Craft;
use craft\base\conditions\BaseDateRangeConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use yii\base\NotSupportedException;

class HasOrdersInDateRange extends BaseDateRangeConditionRule implements ElementConditionRuleInterface
{
    /**
     * @return string
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Has Orders in Date Range');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['hasOrdersIsDateRange'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Orders in date range condition rule does not support queries');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return Order::find()
            ->customerId($element->id)
            ->isCompleted()
            ->dateOrdered($this->queryParamValue())
            ->exists();
    }
}
