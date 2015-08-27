<?php
namespace Craft;

use Market\Interfaces\Purchasable;
use Market\Helpers\MarketDbHelper;

/**
 * Class Market_PurchasableService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_PurchasableService extends BaseApplicationComponent
{

	/**
	 * Saves the element and the purchasable. Use this function where you would usually
	 * use `craft()->elements->saveElement()`
	 *
	 * @param BaseElementModel $model
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function saveElement (BaseElementModel $model)
	{
		MarketDbHelper::beginStackedTransaction();
		try
		{
			if ($success = craft()->elements->saveElement($model))
			{
				if (!$model instanceof Purchasable)
				{
					throw new Exception('Trying to save a purchasable element that is not a purchasable.');
				}

				$id = $model->getPurchasableId();
				$price = $model->getPrice();
				$sku = $model->getSku();

				$purchasable = Market_PurchasableRecord::model()->findById($id);

				if (!$purchasable)
				{
					$purchasable = new Market_PurchasableRecord();
				}

				$purchasable->id = $id;
				$purchasable->price = $price;
				$purchasable->sku = $sku;

				$success = $purchasable->save();

				if (!$success){
					$model->addErrors($purchasable->getErrors());
					MarketDbHelper::rollbackStackedTransaction();
					return $success;
				}

				MarketDbHelper::commitStackedTransaction();

			}
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		return $success;
	}
}