<?php

namespace Craft;

/**
 * Class Market_ProductService
 *
 * @package Craft
 */
class Market_ProductService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_ProductModel
	 */
	public function getById($id)
	{
		$product = Market_ProductRecord::model()->findById($id);

		return Market_ProductModel::populateModel($product);
	}

	/**
	 * Calculates product->variants->salePrice field for all variants of all
	 * products
	 *
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Market_ProductModel[]
	 */
	public function getAllWithSales($criteria = [])
	{
		$products = Market_ProductRecord::model()->findAll($criteria);
		if (!$products) {
			return [];
		}

		$models = Market_ProductModel::populateModels($products);
		$sales  = craft()->market_sale->getForProducts($models);

		foreach ($models as $product) {
			$this->applySales($product, $sales);
		}

		return $models;
	}

	/**
	 * @param Market_ProductModel $product
	 * @param Market_SaleModel[]  $sales
	 */
	private function applySales(Market_ProductModel $product, array $sales)
	{
		foreach ($sales as $sale) {
			if (craft()->market_sale->matchProduct($product, $sale)) {
				foreach ($product->variants as $variant) {
					$variant->salePrice = $variant->price + $sale->calculateTakeoff($variant->price);
					if ($variant->salePrice < 0) {
						$variant->salePrice = 0;
					}
				}
			}
		}
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
		craft()->db->createCommand()->delete('market_product_optiontypes', array('productId' => $productId));

		if ($optionTypeIds) {
			if (!is_array($optionTypeIds)) {
				$optionTypeIds = array($optionTypeIds);
			}

			$values = array();
			foreach ($optionTypeIds as $optionTypeId) {
				$values[] = array($optionTypeId, $productId);
			}

			craft()->db->createCommand()->insertAll('market_product_optiontypes', array('optionTypeId', 'productId'), $values);
		}
	}
}