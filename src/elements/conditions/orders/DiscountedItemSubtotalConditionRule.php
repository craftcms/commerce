<?php

namespace  craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use yii\base\NotSupportedException;
use yii\db\QueryInterface;

/**
 * Item Subtotal With Discounts Applied Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 *
 * @property-read float|int $orderAttributeValue
 */
class DiscountedItemSubtotalConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    public string $orderAttribute = 'itemSubtotal';

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Discounted Item Subtotal');
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(QueryInterface $query): void
    {
        throw new NotSupportedException('Discounted Item Subtotal condition rule does not support queries');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $discountAdjustments = [];
        $discountAdjusters = Plugin::getInstance()->getOrderAdjustments()->getDiscountAdjusters();
        foreach ($discountAdjusters as $discountAdjuster) {
            /** @var AdjusterInterface $discountAdjuster */
            $adjuster = new $discountAdjuster();
            $discountAdjustments = array_merge($discountAdjustments, $adjuster->adjust($element));
        }

        $discountAmount = 0;
        foreach ($discountAdjustments as $adjustment) {
            $discountAmount += $adjustment->amount;
        }

        $itemTotal = $element->getItemSubtotal() + $discountAmount;

        return $this->matchValue($itemTotal);
    }
}
