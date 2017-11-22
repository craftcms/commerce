<?php

namespace craft\commerce\base;

use craft\commerce\models\LineItem;

/**
 * Interface Purchasable
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
interface PurchasableInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the Id of the Purchasable element that should be added to the line item.
     * This elements model should meet the Purchasable Interface.
     *
     * @return int
     */
    public function getPurchasableId(): int;

    /**
     * This is an array of data that should be saved in a serialized way to the line item.
     *
     * Use it as a way to store data on the lineItem even after the purchasable may be deleted.
     * You may want to return all attributes of your purchasable elementType like this: ```$this->getAttributes()``` as well as any additional data.
     *
     * In addition to the data you supply we always overwrite `sku`, `price`, and `description` keys with the data your interface methods return.
     *
     * Example: return ['ticketType' => 'full', 'location' => 'N'];
     *
     * @return array
     */
    public function getSnapshot(): array;

    /**
     * This is the base price the item will be added to the line item with.
     *
     * @return float decimal(14,4)
     */
    public function getPrice(): float;

    /**
     * This must be a unique code. Unique as per the commerce_purchasables table.
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * This would usually be your elements title or any additional descriptive information.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Returns a Craft Commerce tax category id
     *
     * @return int
     */
    public function getTaxCategoryId(): int;

    /**
     * Returns a Craft Commerce shipping category id
     *
     * @return int
     */
    public function getShippingCategoryId(): int;

    /**
     * Returns whether the purchasable is still available for purchase.
     *
     * @return bool
     */
    public function getIsAvailable(): bool;

    /**
     * Populates the line item when this purchasable is found on it. Called when
     * Purchasable is added to the cart and when the cart recalculates.
     *
     * This is your chance to modify the weight, height, width, length, price
     * and saleAmount. This is called before any onPopulateLineItem event listener.
     *
     * @param LineItem $lineItem
     *
     * @return void
     */
    public function populateLineItem(LineItem $lineItem);

    /**
     * Any validation rules this purchasable required the line item to have.
     *
     * @param LineItem $lineItem
     *
     * @return array
     */
    public function getLineItemRules(LineItem $lineItem): array;

    /**
     * Lets the system know if this purchasable has free shipping.
     *
     * @return bool
     */
    public function hasFreeShipping(): bool;

    /**
     * Lets the system know if this purchasable can be subject to discounts or sales.
     *
     * @return bool
     */
    public function getIsPromotable(): bool;
}
