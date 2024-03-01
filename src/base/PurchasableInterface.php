<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;

/**
 * Interface Purchasable
 *
 * @phpstan-require-extends Purchasable
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface PurchasableInterface extends ElementInterface
{
    /**
     * Returns the store for the current instance of the purchasable.
     *
     * @return Store
     */
    public function getStore(): Store;

    /**
     * Returns the store ID for the current instance of the purchasable.
     *
     * @return int
     */
    public function getStoreId(): int;


    /**
     * Returns the live price including catalog rule pricing.
     *
     * @return float|null decimal(14,4)
     */
    public function getPrice(): ?float;

    /**
     * Returns the live promotional price including the catalog rule pricing.
     *
     * @return float|null decimal(14,4)
     * @since 5.0.0
     */
    public function getPromotionalPrice(): ?float;

    /**
     * Returns the actual price the purchasable will be sold for.
     *
     * @return float|null decimal(14,4)
     */
    public function getSalePrice(): ?float;

    /**
     * Returns a unique code. Unique as per the commerce_purchasables table.
     */
    public function getSku(): string;

    /**
     * Returns your element's title or any additional descriptive information.
     */
    public function getDescription(): string;

    /**
     * Returns the purchasable's tax category.
     */
    public function getTaxCategory(): TaxCategory;

    /**
     * Returns the purchasable's shipping category.
     */
    public function getShippingCategory(): ShippingCategory;

    /**
     * Returns whether the purchasable is currently available for purchase.
     */
    public function getIsAvailable(): bool;

    /**
     * Populates the line item when this purchasable is found on it. Called when
     * Purchasable is added to the cart and when the cart recalculates.
     * This is your chance to modify the weight, height, width, length, price
     * and saleAmount. This is called before any LineItems::EVENT_POPULATE_LINE_ITEM event listeners.
     */
    public function populateLineItem(LineItem $lineItem): void;

    /**
     * Returns an array of data that is serializable to json for storing a line
     * item at time of adding to the cart or order.
     */
    public function getSnapshot(): array;

    /**
     * Returns any validation rules this purchasable required the line item to have.
     *
     * @param LineItem $lineItem
     * @return array
     */
    public function getLineItemRules(LineItem $lineItem): array;

    /**
     * Runs any logic needed for this purchasable after it was on an order that was just completed (not when an order was paid, although paying an order will complete it).
     *
     * This is called for each line item the purchasable was contained within.
     *
     * @param Order $order
     * @param LineItem $lineItem
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem): void;

    /**
     * Returns whether this purchasable has free shipping.
     */
    public function hasFreeShipping(): bool;

    /**
     * Returns whether this purchasable can be shipped and whether it is counted in shipping calculations.
     */
    public function getIsShippable(): bool;

    /**
     * Returns whether this purchasable is exempt from taxes.
     */
    public function getIsTaxable(): bool;

    /**
     * Returns whether this purchasable can be subject to discounts or sales.
     */
    public function getIsPromotable(): bool;

    /**
     * Returns the source param used for knowing if a promotion category is related to this purchasable.
     *
     * @return mixed
     */
    public function getPromotionRelationSource(): mixed;
}
