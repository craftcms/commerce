<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_SaleService
 *
 * @package Craft
 */
class Market_SaleService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 * @return Market_SaleModel
	 */
	public function getById($id)
	{
		$record = Market_SaleRecord::model()->findById($id);
		return Market_SaleModel::populateModel($record);
	}

	/**
	 * Getting all sales applicable for the current user and given product
	 *
	 * @param Market_ProductModel $product
	 * @return Market_SaleModel[]
	 */
	public function getForProduct(Market_ProductModel $product)
	{
		$productIds     = [$product->id];
		$productTypeIds = [$product->typeId];

		return $this->getAllByConditions($productIds, $productTypeIds);
	}

	/**
	 * @param $productIds
	 * @param $productTypeIds
	 *
	 * @return Market_SaleModel[]
	 */
	private function getAllByConditions($productIds, $productTypeIds)
	{
		$criteria        = new \CDbCriteria();
		$criteria->group = 't.id';
		$criteria->addCondition('t.enabled = 1');
		$criteria->addCondition('t.dateFrom IS NULL OR t.dateFrom <= NOW()');
		$criteria->addCondition('t.dateTo IS NULL OR t.dateTo >= NOW()');

		$criteria->join = 'LEFT JOIN {{' . Market_SaleProductRecord::model()->getTableName() . '}} sp ON sp.saleId = t.id ';
		$criteria->join .= 'LEFT JOIN {{' . Market_SaleProductTypeRecord::model()->getTableName() . '}} spt ON spt.saleId = t.id ';
		$criteria->join .= 'LEFT JOIN {{' . Market_SaleUserGroupRecord::model()->getTableName() . '}} sug ON sug.saleId = t.id ';

		if ($productIds) {
			$list = implode(',', $productIds);
			$criteria->addCondition("sp.productId IN ($list) OR t.allProducts = 1");
		} else {
			$criteria->addCondition("t.allProducts = 1");
		}

		if ($productTypeIds) {
			$list = implode(',', $productTypeIds);
			$criteria->addCondition("spt.productTypeId IN ($list) OR t.allProductTypes = 1");
		} else {
			$criteria->addCondition("t.allProductTypes = 1");
		}

		$groupIds = craft()->market_discount->getCurrentUserGroups();
		if ($groupIds) {
			$list = implode(',', $groupIds);
			$criteria->addCondition("sug.userGroupId IN ($list) OR t.allGroups = 1");
		} else {
			$criteria->addCondition("t.allGroups = 1");
		}

		//searching
		return $this->getAll($criteria);
	}

	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Market_SaleModel[]
	 */
	public function getAll($criteria = [])
	{
		$records = Market_SaleRecord::model()->findAll($criteria);

		return Market_SaleModel::populateModels($records);
	}

	/**
	 * @param Market_VariantModel $variant
	 *
	 * @return Market_SaleModel[]
	 */
	public function getForVariant(Market_VariantModel $variant)
	{
		$productIds     = [$variant->productId];
		$productTypeIds = [$variant->product->typeId];

		return $this->getAllByConditions($productIds, $productTypeIds);
	}

	/**
	 * @param Market_ProductModel $product
	 * @param Market_SaleModel    $sale
	 *
	 * @return bool
	 */
	public function matchProduct(Market_ProductModel $product, Market_SaleModel $sale)
	{
		if (!$sale->allProducts && !in_array($product->id, $sale->getProductsIds())) {
			return false;
		}

		if (!$sale->allProductTypes && !in_array($product->typeId, $sale->getProductTypesIds())) {
			return false;
		}

		$userGroups = craft()->market_discount->getCurrentUserGroups();
		if (!$sale->allGroups && !array_intersect($userGroups, $sale->getGroupsIds())) {
			return false;
		}

		return true;
	}

	/**
	 * @param Market_SaleModel $model
	 * @param array            $groups       ids
	 * @param array            $productTypes ids
	 * @param array            $products     ids
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save(Market_SaleModel $model, array $groups, array $productTypes, array $products)
	{
		if ($model->id) {
			$record = Market_SaleRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No sale exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_SaleRecord();
		}

		$fields = ['id', 'name', 'description', 'dateFrom', 'dateTo', 'discountType', 'discountAmount', 'enabled'];
		foreach ($fields as $field) {
			$record->$field = $model->$field;
		}

		$record->allGroups       = $model->allGroups = empty($groups);
		$record->allProductTypes = $model->allProductTypes = empty($productTypes);
		$record->allProducts     = $model->allProducts = empty($products);

		$record->validate();
		$model->addErrors($record->getErrors());

		MarketDbHelper::beginStackedTransaction();
		try {
			if (!$model->hasErrors()) {
				$record->save(false);
				$model->id = $record->id;

				Market_SaleUserGroupRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);
				Market_SaleProductRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);
				Market_SaleProductTypeRecord::model()->deleteAllByAttributes(['saleId' => $model->id]);

				foreach ($groups as $groupId) {
					$relation             = new Market_SaleUserGroupRecord;
					$relation->attributes = ['userGroupId' => $groupId, 'saleId' => $model->id];
					$relation->insert();
				}

				foreach ($productTypes as $productTypeId) {
					$relation             = new Market_SaleProductTypeRecord;
					$relation->attributes = ['productTypeId' => $productTypeId, 'saleId' => $model->id];
					$relation->insert();
				}

				foreach ($products as $productId) {
					$relation             = new Market_SaleProductRecord;
					$relation->attributes = ['productId' => $productId, 'saleId' => $model->id];
					$relation->insert();
				}

				MarketDbHelper::commitStackedTransaction();

				return true;
			}
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		MarketDbHelper::rollbackStackedTransaction();

		return false;
	}

	/**
	 * @param int $id
	 */
	public function deleteById($id)
	{
		Market_SaleRecord::model()->deleteByPk($id);
	}
}