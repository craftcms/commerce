<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\elements\Order;

/**
 * Interface ShippingRule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface ShippingRuleInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Returns whether this rule a match on the order. If false is returned, the shipping engine tries the next rule.
     *
     * @param Order $order
     * @return bool
     */
    public function matchOrder(Order $order): bool;

    /**
     * Returns whether this shipping rule is enabled for listing and selection.
     *
     * @return bool
     */
    public function getIsEnabled(): bool;

    /**
     * Returns this data as json on the order's shipping adjustment.
     *
     * @return mixed
     */
    public function getOptions();

    /**
     * Returns the percentage rate that is multiplied per line item subtotal.
     * Zero will not make any changes.
     *
     * @param int|null $shippingCategory the shipping category for the rate requested. A null category should use the default shipping category set up in Craft Commerce.
     * @return float
     */
    public function getPercentageRate($shippingCategory): float;

    /**
     * Returns the flat rate that is multiplied per qty.
     * Zero will not make any changes.
     *
     * @param int|null $shippingCategory the shipping category for the rate requested. A null category should use the default shipping category set up in Craft Commerce.
     * @return float
     */
    public function getPerItemRate($shippingCategory): float;

    /**
     * Returns the rate that is multiplied by the line item's weight.
     * Zero will not make any changes.
     *
     * @param int|null $shippingCategory the shipping category for the rate requested. A null category should use the default shipping category set up in Craft Commerce.
     * @return float
     */
    public function getWeightRate($shippingCategory): float;

    /**
     * Returns a base shipping cost. This is added at the order level.
     * Zero will not make any changes.
     *
     * @return float
     */
    public function getBaseRate(): float;

    /**
     * Returns a max cost this rule should ever apply.
     * If the total of your rates as applied to the order are greater than this, an order level adjustment is made to reduce the shipping amount on the order.
     *
     * @return float
     */
    public function getMaxRate(): float;

    /**
     * Returns a min cost this rule should have applied.
     * If the total of your rates as applied to the order are less than this, the baseShippingCost
     * on the order is modified to meet this min rate.
     * Zero will not make any changes.
     *
     * @return float
     */
    public function getMinRate(): float;

    /**
     * Returns a description of the rates applied by this rule;
     * Zero will not make any changes.
     *
     * @return string
     */
    public function getDescription(): string;
}
