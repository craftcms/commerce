<?php

namespace Craft;

/**
 * Class Market_OrderAdjustmentService
 *
 * @package Craft
 */
class Market_OrderAdjustmentService extends BaseApplicationComponent
{	
    /**
     * @param Market_OrderAdjustmentModel $model
     *
     * @return bool
     * @throws Exception
     */
    public function save(Market_OrderAdjustmentModel $model)
    {
        if ($model->id) {
            $record = Market_OrderAdjustmentRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No order Adjustment exists with the ID “{id}”', ['id' => $model->id]));
            }
        } else {
            $record = new Market_OrderAdjustmentRecord();
        }

        $fields = ['name', 'type', 'rate', 'amount', 'include', 'orderId'];
        foreach($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            $record->save(false);
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $orderId
     * @return int
     */
    public function deleteAllByOrderId($orderId)
    {
        return Market_OrderAdjustmentRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
    }

	/**
	 * @return Market_AddressModel[]
	 */
//	public function getAll()
//	{
//		$records = Market_AddressRecord::model()->with('country', 'state')->findAll(array('order' => 't.name'));
//
//		return Market_AddressModel::populateModels($records);
//	}
//
//	/**
//	 * @param int $id
//	 *
//	 * @return Market_AddressModel
//	 */
//	public function getById($id)
//	{
//		$record = Market_AddressRecord::model()->findById($id);
//
//		return Market_AddressModel::populateModel($record);
//	}
//
//	/**
//	 * @param int $id
//	 * @return Market_AddressModel[]
//	 */
//	public function getByCustomerId($id)
//	{
//		$addresses = Market_AddressRecord::model()->findAll([
//			'join' => 'JOIN craft_market_customeraddresses cmca ON cmca.addressId = t.id',
//			'condition' => 'cmca.customerId = :id',
//			'params' => ['id' => $id],
//		]);
//
//		return Market_AddressModel::populateModels($addresses);
//	}

}