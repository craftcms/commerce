<?php

namespace craft\commerce\conditions\discounts;

use craft\commerce\elements\Order;
use craft\conditions\ConditionInterface;

/**
 * Discount Order Condition Interface
 *
 * @since 4.0.0
 */
interface DiscountOrderConditionInterface extends ConditionInterface
{
    /**
     * Does the condition match the order. This will call each rule and match.
     *
     * @param Order $order
     * @return bool
     */
    public function matchOrder(Order $order): bool;
}