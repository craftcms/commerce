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
     * @param array|\CDbCriteria $criteria
     * @return Market_SaleModel[]
     */
	public function getAll($criteria = [])
	{
		$records = Market_SaleRecord::model()->findAll($criteria);
		return Market_SaleModel::populateModels($records);
	}

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
     * @param Market_SaleModel $model
     * @param array $groups ids
     * @param array $productTypes ids
     * @param array $products ids
     * @return bool
     * @throws \Exception
     */
	public function save(Market_SaleModel $model, array $groups, array $productTypes, array $products)
	{
		if ($model->id) {
			$record = Market_SaleRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No sale exists with the ID “{id}”', array('id' => $model->id)));
			}
		} else {
			$record = new Market_SaleRecord();
		}

        $fields = ['id', 'name', 'description', 'dateFrom', 'dateTo', 'discountType', 'discountAmount', 'enabled'];
        foreach($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->allGroups = $model->allGroups = empty($groups);
        $record->allProductTypes = $model->allProductTypes = empty($productTypes);
        $record->allProducts = $model->allProducts = empty($products);

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

                foreach($groups as $groupId) {
                    $relation = new Market_SaleUserGroupRecord;
                    $relation->attributes = ['userGroupId' => $groupId, 'saleId' => $model->id];
                    $relation->insert();
                }
                
                foreach($productTypes as $productTypeId) {
                    $relation = new Market_SaleProductTypeRecord;
                    $relation->attributes = ['productTypeId' => $productTypeId, 'saleId' => $model->id];
                    $relation->insert();
                }
                
                foreach($products as $productId) {
                    $relation = new Market_SaleProductRecord;
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