<?php
namespace craft\commerce\base;

use craft\base\Element;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;

/**
 * Base Purchasable
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
abstract class Purchasable extends Element implements craft\commerce\base\PurchasableInterface
{

    /**
     * Returns the Id of the Purchasable element that should be added to the line item.
     * This elements model should meet the Purchasable Interface.
     *
     * @return int
     */
    public function getPurchasableId()
    {
        throw new NotImplementedException();
    }

    /**
     * Gathers data to be serialized and saved to the lineItem when adding to the cart.
     * Include any information that might be useful after the purchasable has been deleted.
     *
     * @return array
     */
    public function getSnapshot()
    {
        return [];
    }

    /**
     * This is the base price the item will be added to the line item with.
     *
     * @return float decimal(14,4)
     */
    public function getPrice()
    {
        throw new NotImplementedException();
    }

    /**
     * This must be a unique code. A unique SKU as per the commerce_purchasables table.
     *
     * @return string
     */
    public function getSku()
    {
        throw new NotImplementedException();
    }

    /**
     * This would usually be your elements title or any additional descriptive information.
     *
     * @return string
     */
    public function getDescription()
    {
        return "";
    }

    /**
     * Returns a Craft Commerce tax category id
     *
     * @return int
     */
    public function getTaxCategoryId()
    {
        return Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
    }

    /**
     * Returns a Craft Commerce shipping category id
     *
     * @return int
     */
    public function getShippingCategoryId()
    {
        return Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory()->id;
    }


    /**
     * Returns if a purchasable is available
     *
     * @return int
     */
    public function getIsAvailable()
    {
        return true;
    }

    /**
     * Populates the line item when this purchasable is found on it. Called when Purchasable is added to the cart and when the cart recalculates.
     *
     * This is your chance to modify the weight, height, width, length, price and saleAmount.
     * This is called before any onPopulateLineItem event listener.
     *
     * @param \craft\commerce\models\LineItem $lineItem
     *
     * @return null
     */
    public function populateLineItem(LineItem $lineItem)
    {
        return;
    }

    /**
     * Validates this purchasable for the line item it is on. Called when Purchasable is added to the cart.
     *
     * You can add model errors to the line item like this: `$lineItem->addError('qty', $errorText);`
     *
     * @param \craft\commerce\models\LineItem $lineItem
     *
     * @return mixed
     */
    public function validateLineItem(LineItem $lineItem)
    {
        return true;
    }

    /**
     * Lets the system know if this purchasable has free shipping.
     *
     * @return bool
     */
    public function hasFreeShipping()
    {
        return false;
    }

    /**
     * Lets the system know if this purchasable can be subject to discounts or sales.
     *
     * @return bool
     */
    public function getIsPromotable()
    {
        return true;
    }
}