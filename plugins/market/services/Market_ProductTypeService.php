<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

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

		$productTypeRecord->name     = $productType->name;
		$productTypeRecord->handle   = $productType->handle;
		$productTypeRecord->hasUrls  = $productType->hasUrls;
		$productTypeRecord->template = $productType->template;

		// Set flag if urlFormat changed so we can update all product elements.
		if ($productTypeRecord->urlFormat != $productType->urlFormat) {
			$urlFormatChanged = true;
		}
		$productTypeRecord->urlFormat = $productType->urlFormat;

		$productTypeRecord->validate();
		$productType->addErrors($productTypeRecord->getErrors());

		if (!$productType->hasErrors()) {
			MarketDbHelper::beginStackedTransaction();
			try {

				// Product Field Layout
				if (!$isNewProductType && $oldProductType->fieldLayoutId) {
					// Drop the old field layout
					craft()->fields->deleteLayoutById($oldProductType->fieldLayoutId);
				}
				// Save the new one
				$fieldLayout = $productType->asa('productFieldLayout')->getFieldLayout();
				craft()->fields->saveLayout($fieldLayout);
				$productType->fieldLayoutId       = $fieldLayout->id;
				$productTypeRecord->fieldLayoutId = $fieldLayout->id;

				// Save it!
				$productTypeRecord->save(false);

				// Now that we have a product type ID, save it on the model
				if (!$productType->id) {
					$productType->id = $productTypeRecord->id;
				}

				//Refresh all urls for products of same type if urlFormat changed.
				if ($urlFormatChanged) {
					$criteria         = craft()->elements->getCriteria('Market_Product');
					$criteria->typeId = $productType->id;
					$products         = $criteria->find();
					foreach ($products as $key => $product) {
						if ($product && $product->getContent()->id) {
							craft()->elements->updateElementSlugAndUri($product, false, false);
						}
					}
				}

				MarketDbHelper::commitStackedTransaction();

			} catch (\Exception $e) {
				MarketDbHelper::rollbackStackedTransaction();

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
		MarketDbHelper::beginStackedTransaction();
		try {
			$productType = Market_ProductTypeRecord::model()->findById($id);

			$query      = craft()->db->createCommand()
				->select('id')
				->from('market_products')
				->where(['typeId' => $productType->id]);
			$productIds = $query->queryColumn();

			craft()->elements->deleteElementById($productIds);
			craft()->fields->deleteLayoutById($productType->fieldLayoutId);

			$affectedRows = $productType->delete();

			MarketDbHelper::commitStackedTransaction();

			return (bool)$affectedRows;
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();

			throw $e;
		}
	}

	// Need to have a separate controller action and service method
	// since you cant have 2 field layout editors on one POST.
	public function saveVariantFieldLayout($productType)
	{
		$productTypeRecord = Market_ProductTypeRecord::model()->findById($productType->id);

		if ($productType->variantFieldLayoutId) {
			craft()->fields->deleteLayoutById($productType->variantFieldLayoutId);
		}

		// Save the new one
		$variantFieldLayout = $productType->asa('variantFieldLayout')->getFieldLayout();
		craft()->fields->saveLayout($variantFieldLayout);
		$productTypeRecord->variantFieldLayoutId = $variantFieldLayout->id;

		$productTypeRecord->save(false);

		return true;

	}
}