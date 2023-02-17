<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\commerce\base\StoreTrait;
use craft\commerce\elements\Order;
use craft\elements\db\ElementQueryInterface;
use Imagine\Exception\NotSupportedException;

/**
 * Shipping Rule Order query condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class ShippingRuleOrderCondition extends OrderCondition
{
    use StoreTrait;

    /**
     * @inheritdoc
     */
    public ?string $elementType = Order::class;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['storeId'], 'safe'];

        return $rules;
    }

    /**
     * @return array
     */
    protected function config(): array
    {
        return array_merge(parent::config(), $this->toArray(['storeId']));
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Shipping Rule Order Condition does not support queries');
    }

    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        $ruleTypes = parent::conditionRuleTypes();


        foreach ($ruleTypes as $key => $ruleType) {
            if (in_array($ruleType, [
                DateOrderedConditionRule::class,
                CompletedConditionRule::class,
                PaidConditionRule::class,
                OrderStatusConditionRule::class,
                ShippingMethodConditionRule::class,
            ])) {
                unset($ruleTypes[$key]);
            }
        }

        $ruleTypes[] = DiscountedItemSubtotalConditionRule::class;
        $ruleTypes[] = ShippingAddressZoneConditionRule::class;

        return $ruleTypes;
    }
}
