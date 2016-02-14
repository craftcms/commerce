<?php

namespace Commerce\Interfaces;

/**
 * Base Purchasable
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Interfaces
 * @since     1.0
 */
abstract class Commerce_BasePurchasable implements Purchasable
{


	/**
	 * This is the base price the item will be added to the line item with.
	 *
	 * @return float decimal(14,4)|null
	 */
	public function getPrice()
	{
		return null;
	}

	/**
	 * Returns a Craft Commerce tax category id.
	 *
	 * @return int
	 */
	public function getTaxCategoryId()
	{
		return craft()->commerce_taxCategories->getDefaultTaxCategoryId();
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
		return false;
	}

}
