<?php

namespace Craft;
use Market\Helpers\MarketDbHelper;

/**
 * Class Market_DiscountService
 *
 * @package Craft
 */
class Market_DiscountService extends BaseApplicationComponent
{
    /**
     * @param array|\CDbCriteria $criteria
     * @return Market_DiscountModel[]
     */
	public function getAll($criteria = [])
	{
		$records = Market_DiscountRecord::model()->findAll($criteria);
		return Market_DiscountModel::populateModels($records);
	}

	/**
	 * @param int $id
	 * @return Market_DiscountModel
	 */
	public function getById($id)
	{
		$record = Market_DiscountRecord::model()->findById($id);
		return Market_DiscountModel::populateModel($record);
	}

    public function getUserGroupsIds()
    {

    }

//    /**
//     * @param array $attr
//     * @return Market_DiscountModel
//     */
//    public function getByAttributes(array $attr)
//    {
//        $record = Market_DiscountRecord::model()->findByAttributes($attr);
//        return Market_DiscountModel::populateModel($record);
//    }
//
//	/**
//	 * Simple list for using in forms
//	 * @return array [id => name]
//	 */
//	public function getFormList()
//	{
//		$discounts = $this->getAll();
//		return \CHtml::listData($discounts, 'id', 'name');
//	}

    /**
     * @param Market_DiscountModel $model
     * @param array $groups ids
     * @param array $productTypes ids
     * @param array $products ids
     * @return bool
     * @throws \Exception
     */
	public function save(Market_DiscountModel $model, array $groups, array $productTypes, array $products)
	{
		if ($model->id) {
			$record = Market_DiscountRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No discount exists with the ID “{id}”', array('id' => $model->id)));
			}
		} else {
			$record = new Market_DiscountRecord();
		}

        foreach($model->attributeNames() as $field) {
            $record->$field = $model->$field;
        }

		$record->validate();
		$model->addErrors($record->getErrors());

        MarketDbHelper::beginStackedTransaction();
        try {
            if (!$model->hasErrors()) {
                $record->save(false);
                $model->id = $record->id;

                Market_DiscountUserGroupRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);
                Market_DiscountProductRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);
                Market_DiscountProductTypeRecord::model()->deleteAllByAttributes(['discountId' => $model->id]);

                foreach($groups as $groupId) {
                    $relation = new Market_DiscountUserGroupRecord;
                    $relation->attributes = ['userGroupId' => $groupId, 'discountId' => $model->id];
                    $relation->insert();
                }
                
                foreach($productTypes as $productTypeId) {
                    $relation = new Market_DiscountProductTypeRecord;
                    $relation->attributes = ['productTypeId' => $productTypeId, 'discountId' => $model->id];
                    $relation->insert();
                }
                
                foreach($products as $productId) {
                    $relation = new Market_DiscountProductRecord;
                    $relation->attributes = ['productId' => $productId, 'discountId' => $model->id];
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
		Market_DiscountRecord::model()->deleteByPk($id);
	}
}