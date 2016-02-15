<?php

namespace Commerce\Base;

use Craft\BaseElementModel;
use Commerce\Interfaces\Purchasable as PurchasableInterface;
use Commerce\Exception\NotImplementedException;

/**
 * Base Purchasable
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Base
 * @since     1.0
 */
abstract class Purchasable extends BaseElementModel implements PurchasableInterface
{

	/**
	 * Returns the Id of the Purchasable element that should be added to the lineitem.
	 * This elements model should meet the Purchasable Interface.
	 *
	 * @return int
	 */
	public function getPurchasableId()
	{
		throw new NotImplementedException();
	}

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
	 * This must be a unique code. Unique as per the commerce_purchasables table.
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
		return craft()->commerce_taxCategories->getDefaultTaxCategoryId();
	}

	/**
	 * Validates this purchasable for the line item it is on. Called when Purchasable is added to the cart.
	 *
	 * You can add model errors to the line item like this: `$lineItem->addError('qty', $errorText);`
	 *
	 * @param \Craft\Commerce_LineItemModel $lineItem
	 *
	 * @return mixed
	 */
	public function validateLineItem(\Craft\Commerce_LineItemModel $lineItem)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function hasFreeShipping()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function getIsPromotable()
	{
		return true;
	}
}