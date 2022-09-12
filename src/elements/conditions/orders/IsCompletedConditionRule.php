<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

/**
 * Is Completed Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 *
 * @property-read float|int $orderAttributeValue
 */
class IsCompletedConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Is Completed');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @param ElementQueryInterface $query
     * @return void
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $query->isCompleted($this->value);
    }

    /**
     * @param ElementInterface $element
     * @return bool
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $element->isCompleted === $this->value;
    }
}
