<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Class Commerce_ProductService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ProductService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @param int $localeId
	 *
	 * @return Commerce_ProductModel
	 */
	public function getById ($id, $localeId = null)
	{
		return craft()->elements->getElementById($id, 'Commerce_Product', $localeId);
	}


	/**
	 * @param Commerce_ProductModel $product
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function save (Commerce_ProductModel $product)
	{

		if (!$product->id)
		{
			$record = new Commerce_ProductRecord();
		}
		else
		{
			$record = Commerce_ProductRecord::model()->findById($product->id);

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

		CommerceDbHelper::beginStackedTransaction();
		try
		{
			if (!$product->hasErrors())
			{
				if (craft()->elements->saveElement($product))
				{
					$record->id = $product->id;
					$record->save(false);

					CommerceDbHelper::commitStackedTransaction();

					return true;
				}
			}
		}
		catch (\Exception $e)
		{
			CommerceDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		CommerceDbHelper::rollbackStackedTransaction();

		return false;
	}


	/**
	 * @param Commerce_ProductModel $product
	 *
	 * @return bool
	 * @throws \CDbException
	 */
	public function delete ($product)
	{
		$product = Commerce_ProductRecord::model()->findById($product->id);
		if ($product)
		{
			$variants = craft()->commerce_variant->getAllByProductId($product->id);
			if (craft()->elements->deleteElementById($product->id))
			{
				foreach ($variants as $v)
				{
					craft()->commerce_variant->deleteById($v->id);
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