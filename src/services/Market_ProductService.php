<?php
namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_ProductService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_ProductService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @param int $localeId
	 *
	 * @return Market_ProductModel
	 */
	public function getById ($id, $localeId = null)
	{
		return craft()->elements->getElementById($id, 'Market_Product', $localeId);
	}


	/**
	 * @param Market_ProductModel $product
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function save (Market_ProductModel $product)
	{

		if (!$product->id)
		{
			$record = new Market_ProductRecord();
		}
		else
		{
			$record = Market_ProductRecord::model()->findById($product->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No product exists with the ID “{id}”',
					['id' => $product->id]));
			}
		}

		$record->availableOn = $product->availableOn;
		$record->expiresOn = $product->expiresOn;
		$record->typeId = $product->typeId;
		$record->authorId = $product->authorId;
		$record->promotable = $product->promotable;
		$record->freeShipping = $product->freeShipping;
		$record->taxCategoryId = $product->taxCategoryId;

		$record->validate();
		$product->addErrors($record->getErrors());

		MarketDbHelper::beginStackedTransaction();
		try
		{
			if (!$product->hasErrors())
			{
				if (craft()->elements->saveElement($product))
				{
					$record->id = $product->id;
					$record->save(false);

					MarketDbHelper::commitStackedTransaction();

					return true;
				}
			}
		}
		catch (\Exception $e)
		{
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::rollbackStackedTransaction();

		return false;
	}


	/**
	 * @param Market_ProductModel $product
	 *
	 * @return bool
	 * @throws \CDbException
	 */
	public function delete ($product)
	{
		$product = Market_ProductRecord::model()->findById($product->id);
		if ($product)
		{
			$variants = craft()->market_variant->getAllByProductId($product->id);
			if (craft()->elements->deleteElementById($product->id))
			{
				foreach ($variants as $v)
				{
					craft()->market_variant->deleteById($v->id);
				}

				return true;
			}
			else
			{
				return false;
			}
		}
	}

}