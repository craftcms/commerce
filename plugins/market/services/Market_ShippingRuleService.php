<?php

namespace Craft;

/**
 * Class Market_ShippingRuleService
 *
 * @package Craft
 */
class Market_ShippingRuleService extends BaseApplicationComponent
{
	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Market_ShippingRuleModel[]
	 */
	public function getAll($criteria = [])
	{
		$records = Market_ShippingRuleRecord::model()->findAll($criteria);

		return Market_ShippingRuleModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_ShippingRuleModel
	 */
	public function getById($id)
	{
		$record = Market_ShippingRuleRecord::model()->findById($id);

		return Market_ShippingRuleModel::populateModel($record);
	}

	/**
	 * @param Market_ShippingRuleModel $rule
	 * @param Market_OrderModel        $order
	 *
	 * @return bool
	 */
	public function matchOrder(Market_ShippingRuleModel $rule, Market_OrderModel $order)
	{
		if (!$rule->enabled) {
			return false;
		}

		$floatFields = ['minTotal', 'maxTotal', 'minWeight', 'maxWeight', 'maxHeight','minHeight', 'maxLength','minLength', 'maxWidth','minWidth'];
		foreach ($floatFields as $field) {
			$rule->$field *= 1;
		}

		// geographical filters
		if ($rule->countryId && $rule->countryId != $order->shippingAddress->countryId) {
			return false;
		}
		if ($rule->stateId && $rule->state->name != $order->shippingAddress->getStateText()) {
			return false;
		}

		// order qty rules are inclusive (min <= x <= max)
		if ($rule->minQty AND $rule->minQty > $order->totalQty) {
			return false;
		}
		if ($rule->maxQty AND $rule->maxQty < $order->totalQty) {
			return false;
		}

		// order total rules exclude maximum limit (min <= x < max)
		if ($rule->minTotal AND $rule->minTotal > $order->itemTotal) {
			return false;
		}
		if ($rule->maxTotal AND $rule->maxTotal <= $order->itemTotal) {
			return false;
		}

		// order weight rules exclude maximum limit (min <= x < max)
		if ($rule->minWeight AND $rule->minWeight > $order->totalWeight) {
			return false;
		}
		if ($rule->maxWeight AND $rule->maxWeight <= $order->totalWeight) {
			return false;
		}

		// all rules match
		return true;
	}

	/**
	 * @param Market_ShippingRuleModel $model
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save(Market_ShippingRuleModel $model)
	{
		if ($model->id) {
			$record = Market_ShippingRuleRecord::model()->findById($model->id);

			if (!$record) {
				throw new Exception(Craft::t('No shipping rule exists with the ID “{id}”', ['id' => $model->id]));
			}
		} else {
			$record = new Market_ShippingRuleRecord();
		}

		$fields = ['name', 'description', 'countryId', 'stateId', 'methodId', 'enabled', 'minQty', 'maxQty', 'minTotal', 'maxTotal',
			'minWeight', 'maxWeight', 'baseRate', 'perItemRate', 'weightRate', 'percentageRate', 'minRate', 'maxRate'];
		foreach ($fields as $field) {
			$record->$field = $model->$field;
		}

		if (empty($record->priority) && empty($model->priority)) {
			$count            = Market_ShippingRuleRecord::model()->countByAttributes(['methodId' => $model->methodId]);
			$record->priority = $model->priority = $count + 1;
		} elseif ($model->priority) {
			$record->priority = $model->priority;
		} else {
			$model->priority = $record->priority;
		}

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors()) {
			// Save it!
			$record->save(false);

			// Now that we have a record ID, save it on the model
			$model->id = $record->id;

			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param int $id
	 */
	public function deleteById($id)
	{
		Market_ShippingRuleRecord::model()->deleteByPk($id);
	}
}