<?php

namespace Commerce\Interfaces;

use Craft\Commerce_LineItemModel;

/**
 * Interface Purchasable
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Interfaces
 * @since     1.0
 */
interface Purchasable
{

    /**
     * Returns the Id of the Purchasable element that should be added to the lineitem.
     * This elements model should meet the Purchasable Interface.
     *
     * @return int
     */
    public function getPurchasableId();

    /*
     * This is an array of data that should be saved in a serialized way to the line item.
     *
     * Use it as a way to store data on the lineItem even after the purchasable may be deleted.
     * You may want to return all attributes of your purchasable elementType like this: ```$this->getAttributes()``` as well as any additional data.
     *
     * In addition to the data you supply we always overwrite `sku`, `price`, and `description` keys with the data your interface methods return.
     *
     * Example: return ['ticketType' => 'full',
     *                       'location' => 'N'];
     *
     *
     * @return array
     */
    public function getSnapshot();

    /**
     * This is the base price the item will be added to the line item with.
     *
     * @return float decimal(14,4)
     */
    public function getPrice();


    /**
     * This must be a unique code. Unique as per the commerce_purchasables table.
     *
     * @return string
     */
    public function getSku();

    /**
     * This would usually be your elements title or any additional descriptive information.
     *
     * @return string
     */
    public function getDescription();

    /**
     * Returns a Craft Commerce tax category id
     *
     * @return int
     */
    public function getTaxCategoryId();

    /**
     * Returns a Craft Commerce shipping category id
     *
     * @return int
     */
    public function getShippingCategoryId();

    /**
     * Returns whether the purchasable is still available for purchase.
     *
     * @return bool
     */
    public function getIsAvailable();

    /**
     * Populates the line item when this purchasable is found on it. Called when Purchasable is added to the cart and when the cart recalculates.
     *
     * @param \Craft\Commerce_LineItemModel $lineItem
     *
     * @return null
     */
    public function populateLineItem(Commerce_LineItemModel $lineItem);

    /**
     * Validates this purchasable for the line item it is on. Called when Purchasable is added to the cart.
     *
     * You can add model errors to the line item like this: `$lineItem->addError('qty', $errorText);`
     *
     * @param \Craft\Commerce_LineItemModel $lineItem
     *
     * @return mixed
     */
    public function validateLineItem(Commerce_LineItemModel $lineItem);

    /**
     * @return bool
     */
    public function hasFreeShipping();

    /**
     * @return bool
     */
    public function getIsPromotable();

}
