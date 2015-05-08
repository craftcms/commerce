<?php

namespace Craft;
use Market\Helpers\MarketDbHelper;
/**
 * Class Market_ProductService
 *
 * @package Craft
 */
class Market_ProductService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @return Market_ProductModel
	 */
	public function getById($id)
	{
        return craft()->elements->getElementById($id, 'Market_Product');
	}


	/**
	 * @param Market_ProductModel $product
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function save(Market_ProductModel $product)
	{
		if (!$product->id) {
			$record = new Market_ProductRecord();
		} else {
			$record = Market_ProductRecord::model()->findById($product->id);

			if (!$record) {
				throw new Exception(Craft::t('No product exists with the ID “{id}”', ['id' => $product->id]));
			}
		}

		$record->availableOn   = $product->availableOn;
		$record->expiresOn     = $product->expiresOn;
		$record->typeId        = $product->typeId;
		$record->authorId      = $product->authorId;
		$record->taxCategoryId = $product->taxCategoryId;

		$record->validate();
		$product->addErrors($record->getErrors());

		MarketDbHelper::beginStackedTransaction();
		try {
			if (!$product->hasErrors()) {
				if (craft()->elements->saveElement($product)) {
					$record->id = $product->id;
					$record->save(false);

					MarketDbHelper::commitStackedTransaction();

					return true;
				}
			}
		} catch (\Exception $e) {
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
	public function delete($product)
	{
		$product = Market_ProductRecord::model()->findById($product->id);
		if ($product->delete()) {
			craft()->market_variant->disableAllByProductId($product->id);

			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param int $productId
	 *
	 * @return Market_OptionTypeModel[]
	 */
	public function getOptionTypes($productId)
	{
		$product = Market_ProductRecord::model()->with('optionTypes')->findById($productId);

		return Market_OptionTypeModel::populateModels($product->optionTypes);
	}

	/**
	 * Set option types to a product
	 *
	 * @param int   $productId
	 * @param int[] $optionTypeIds
	 *
	 * @return bool
	 */
	public function setOptionTypes($productId, $optionTypeIds)
	{
		craft()->db->createCommand()->delete('market_product_optiontypes', ['productId' => $productId]);

		if ($optionTypeIds) {
			if (!is_array($optionTypeIds)) {
				$optionTypeIds = [$optionTypeIds];
			}

			$values = [];
			foreach ($optionTypeIds as $optionTypeId) {
				$values[] = [$optionTypeId, $productId];
			}

			craft()->db->createCommand()->insertAll('market_product_optiontypes', ['optionTypeId', 'productId'], $values);
		}
	}
}