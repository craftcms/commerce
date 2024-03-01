<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\elements\Order;
use Illuminate\Support\Collection;

/**
 * Interface ShippingMethod
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface ShippingMethodInterface
{
    /**
     * Returns the type of Shipping Method. This might be the name of the plugin or provider.
     * The core shipping methods have type: `Custom`. This is shown in the control panel only.
     */
    public function getType(): string;

    /**
     * Returns the ID of this Shipping Method, if it is managed by Craft Commerce.
     *
     * @return int|null The shipping method ID, or null if it is not managed by Craft Commerce
     */
    public function getId(): ?int;

    /**
     * Returns the name of this Shipping Method as displayed to the customer and in the control panel.
     */
    public function getName(): string;

    /**
     * Returns the unique handle of this Shipping Method.
     */
    public function getHandle(): string;

    /**
     * Returns the control panel URL to manage this method and its rules.
     * An empty string will result in no link.
     */
    public function getCpEditUrl(): string;

    /**
     * Returns an array of rules that meet the `ShippingRules` interface.
     *
     * @return Collection<ShippingRuleInterface> The array of ShippingRules
     */
    public function getShippingRules(): Collection;

    /**
     * Returns whether this shipping method is enabled for listing and selection by customers.
     */
    public function getIsEnabled(): bool;

    public function getPriceForOrder(Order $order): float;

    /**
     * The first matching shipping rule for this shipping method
     */
    public function getMatchingShippingRule(Order $order): ?ShippingRuleInterface;

    /**
     * Is this shipping method available to the order?
     */
    public function matchOrder(Order $order): bool;
}
