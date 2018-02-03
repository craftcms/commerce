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
     * Returns the ID of the Purchasable element that will be be added to the line item.
     * This element should meet the Purchasable Interface.
     *
     * @return int
     */
    public function getPurchasableId(): int;

    /**
     * This is the base price the item will be added to the line item with.
     *
     * @return float decimal(14,4)
     */
    public function getPrice(): float;

    /**
     * This is the price the item will be given the context
     *
     * @return float decimal(14,4)
     */
    public function getLivePrice(): float;

    /**
     * This is the base price the item will be added to the line item with.
     *
     * It provides opportunity to populate the salePrice if sales have not already been applied.
     *
     * @return float decimal(14,4)
     */
    public function getSalePrice(): float;

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
     * Returns the purchasable's tax category ID.
     *
     * @return int
     */
    public function getTaxCategoryId(): int;

    /**
     * Returns the purchasable's shipping category ID.
     *
     * @return int
     */
    public function getShippingCategoryId(): int;

    /**
     * Returns whether the purchasable is currently available for purchase.
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
     */
    public function populateLineItem(LineItem $lineItem);

    /**
     * Validates this purchasable for the line item it is on. Called when Purchasable is added to the cart.
     *
     * You can add model errors to the line item like this: `$lineItem->addError('qty', $errorText);`
     *
     * @param LineItem $lineItem
     */
    public function validateLineItem(LineItem $lineItem);

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

    /**
     * Returns the source param used for knowing if a promotion category is related to this purchasable.
     *
     * @return mixed
     */
    public function getPromotionRelationSource();
}
