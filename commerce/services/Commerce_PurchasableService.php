<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;
use Commerce\Interfaces\Purchasable;

/**
 * Purchasable service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_PurchasableService extends BaseApplicationComponent
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
		if (!$model instanceof Purchasable)
		{
			throw new Exception('Trying to save a purchasable element that is not a purchasable.');
		}

		CommerceDbHelper::beginStackedTransaction();
		try
		{
			if ($success = craft()->elements->saveElement($model))
			{
				$id = $model->getPurchasableId();
				$price = $model->getPrice();
				$sku = $model->getSku();

				$purchasable = Commerce_PurchasableRecord::model()->findById($id);

				if (!$purchasable)
				{
					$purchasable = new Commerce_PurchasableRecord();
				}

				$purchasable->id = $id;
				$purchasable->price = $price;
				$purchasable->sku = $sku;

				$success = $purchasable->save();

				if (!$success)
				{
					$model->addErrors($purchasable->getErrors());
					CommerceDbHelper::rollbackStackedTransaction();

					return $success;
				}

				CommerceDbHelper::commitStackedTransaction();
			}
		}
		catch (\Exception $e)
		{
			CommerceDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		return $success;
	}
}