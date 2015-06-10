<?php

namespace Craft;

/**
 * Class Market_ProductTypeService
 *
 * @package Craft
 */
class Market_ProductTypeService extends BaseApplicationComponent
{
	/**
	 * @return Market_ProductTypeModel[]
	 */
	public function getAll()
	{
		$productTypeRecords = Market_ProductTypeRecord::model()->findAll();

		return Market_ProductTypeModel::populateModels($productTypeRecords);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_ProductTypeModel
	 */
	public function getById($id)
	{
		$productTypeRecord = Market_ProductTypeRecord::model()->findById($id);

		return Market_ProductTypeModel::populateModel($productTypeRecord);
	}

	/**
	 * @param string $handle
	 *
	 * @return Market_ProductTypeModel
	 */
	public function getByHandle($handle)
	{
		$productTypeRecord = Market_ProductTypeRecord::model()->findByAttributes(['handle' => $handle]);

		return Market_ProductTypeModel::populateModel($productTypeRecord);
	}

	/**
	 * @param Market_ProductTypeModel $productType
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_ProductTypeModel $productType)
	{
		$urlFormatChanged = false;

		if ($productType->id) {
			$productTypeRecord = Market_ProductTypeRecord::model()->findById($productType->id);
			if (!$productTypeRecord) {
				throw new Exception(Craft::t('No product type exists with the ID “{id}”', ['id' => $productType->id]));
			}

			$oldProductType   = Market_ProductTypeModel::populateModel($productTypeRecord);
			$isNewProductType = false;
		} else {
			$productTypeRecord = new Market_ProductTypeRecord();
			$isNewProductType  = true;
		}

		$productTypeRecord->name      = $productType->name;
		$productTypeRecord->handle    = $productType->handle;
		$productTypeRecord->hasUrls   = $productType->hasUrls;
		$productTypeRecord->template  = $productType->template;

		// Set flag if urlFormat changed so we can update all product elements.
		if ($productTypeRecord->urlFormat != $productType->urlFormat){
			$urlFormatChanged = true;
		}
		$productTypeRecord->urlFormat = $productType->urlFormat;

		$productTypeRecord->validate();
		$productType->addErrors($productTypeRecord->getErrors());

		if (!$productType->hasErrors()) {
			$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
			try {
				if (!$isNewProductType && $oldProductType->productFieldLayoutId) {
					// Drop the old field layout
					craft()->fields->deleteLayoutById($oldProductType->productFieldLayoutId);
				}

				// Save the new one
				$productFieldLayout = $productType->productFieldLayout;
				craft()->fields->saveLayout($productFieldLayout);

				// Update the calendar record/model with the new layout ID
				$productType->productFieldLayoutId       = $productFieldLayout->id;
				$productTypeRecord->productFieldLayoutId = $productFieldLayout->id;

				// Save it!
				$productTypeRecord->save(false);

				// Now that we have a calendar ID, save it on the model
				if (!$productType->id) {
					$productType->id = $productTypeRecord->id;
				}

				//Refresh all urls for products of same type if urlFormat changed.
				if($urlFormatChanged){
					$criteria = craft()->elements->getCriteria('Market_Product');
					$criteria->typeId = $productType->id;
					$products = $criteria->find();
					foreach ($products as $key => $product)
					{
						if ($product && $product->getContent()->id)
						{
							craft()->elements->updateElementSlugAndUri($product, false, false);
						}
					}
				}

				if ($transaction !== NULL) {
					$transaction->commit();
				}
			} catch (\Exception $e) {
				if ($transaction !== NULL) {
					$transaction->rollback();
				}

				throw $e;
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Deleted a
	 *
	 * @param $id
	 *
	 * @return bool
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function deleteById($id)
	{
		$transaction = craft()->db->getCurrentTransaction() === NULL ? craft()->db->beginTransaction() : NULL;
		try {
			$productType = Market_ProductTypeRecord::model()->findById($id);

			$query      = craft()->db->createCommand()
				->select('id')
				->from('market_products')
				->where(['typeId' => $productType->id]);
			$productIds = $query->queryColumn();

			craft()->elements->deleteElementById($productIds);
			craft()->fields->deleteLayoutById($productType->productFieldLayoutId);

			$affectedRows = $productType->delete();

			if ($transaction !== NULL) {
				$transaction->commit();
			}

			return (bool)$affectedRows;
		} catch (\Exception $e) {
			if ($transaction !== NULL) {
				$transaction->rollback();
			}

			throw $e;
		}
	}

}