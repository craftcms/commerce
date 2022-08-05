<?php

namespace craft\commerce\elements\conditions\customers;

use Craft;
use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

class CustomerDaysSinceLastPurchase extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    /**
     * @return string
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Days since last purchase');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['daysSinceLastPurchase'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Days since last purchase condition rule does not support queries');
    }

    public function matchElement(ElementInterface $element): bool
    {
        // query for the date of the element (customers) last order
        // compare how many days since to $this->valueParam() with $this->matchValue($element->{$this->orderAttribute});
    }
}