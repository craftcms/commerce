<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace  craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseDateRangeConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

/**
 * Date Ordered condition rule.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.0
 */
class DateOrderedConditionRule extends BaseDateRangeConditionRule implements ElementConditionRuleInterface
{

    public function getLabel(): string
    {
        return Craft::t('commerce', 'Date Ordered');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var OrderQuery $query */
        $query->dateOrdered($this->queryParamValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        return $this->matchValue($element->dateOrdered);
    }
}