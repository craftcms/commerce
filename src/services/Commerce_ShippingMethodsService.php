<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Shipping method service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ShippingMethodsService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_ShippingMethodModel
	 */
	public function getById ($id)
	{
		$record = Commerce_ShippingMethodRecord::model()->findById($id);

		return Commerce_ShippingMethodModel::populateModel($record);
	}

	/**
	 * Gets the default method or first available if no default set.
	 */
	public function getDefault ()
	{
		$method = Commerce_ShippingMethodRecord::model()->findByAttributes(['default' => true]);
		if (!$method)
		{
			$records = $this->getAll();
			if (!$records)
			{
				throw new Exception(Craft::t('You have no Shipping Methods set up.'));
			}

			return $records[0];
		}

		return $method;
	}

	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Commerce_ShippingMethodModel[]
	 */
	public function getAll ($criteria = [])
	{
		$records = Commerce_ShippingMethodRecord::model()->findAll($criteria);

		return Commerce_ShippingMethodModel::populateModels($records);
	}

	/**
	 * @return bool
	 */
	public function exists ()
	{
		return Commerce_ShippingMethodRecord::model()->exists();
	}

	/**
	 * @param Commerce_OrderModel $cart
	 *
	 * @return array
	 */
	public function calculateForCart (Commerce_OrderModel $cart)
	{
		$availableMethods = [];
		$methods = $this->getAll(['with' => 'rules']);

		foreach ($methods as $method)
		{
			if ($method->enabled)
			{
				if ($rule = $this->getMatchingRule($cart, $method))
				{
					$amount = $rule->baseRate;
					$amount += $rule->perItemRate * $cart->totalQty;
					$amount += $rule->weightRate * $cart->totalWeight;
					$amount += $rule->percentageRate * $cart->itemTotal;
					$amount = max($amount, $rule->minRate * 1);

					if ($rule->maxRate * 1)
					{
						$amount = min($amount, $rule->maxRate * 1);
					}

					$availableMethods[$method->id] = [
						'name'   => $method->name,
						'amount' => $amount,
					];
				}
			}
		}

		return $availableMethods;
	}

	/**
	 * @param Commerce_OrderModel          $order
	 * @param Commerce_ShippingMethodModel $method
	 *
	 * @return bool|Commerce_ShippingRuleModel
	 */
	public function getMatchingRule (
		Commerce_OrderModel $order,
		Commerce_ShippingMethodModel $method
	)
	{
		foreach ($method->rules as $rule)
		{
			if (craft()->commerce_shippingRules->matchOrder($rule, $order))
			{
				return $rule;
			}
		}

		return false;
	}

	/**
	 * @param Commerce_ShippingMethodModel $model
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save (Commerce_ShippingMethodModel $model)
	{
		if ($model->id)
		{
			$record = Commerce_ShippingMethodRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No shipping method exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Commerce_ShippingMethodRecord();
		}

		$record->name = $model->name;
		$record->enabled = $model->enabled;
		$record->default = $model->default;

		$record->validate();
		$model->addErrors($record->getErrors());

		if (!$model->hasErrors())
		{
			// Save it!
			$record->save(false);

			// Now that we have a record ID, save it on the model
			$model->id = $record->id;

			//If this was the default make all others not the default.
			if ($model->default)
			{
				Commerce_ShippingMethodRecord::model()->updateAll(['default' => 0],
					'id != ?', [$record->id]);
			}

			return true;
		}
		else
		{
			return false;
		}
	}


	/**
	 * @param $model
	 *
	 * @return bool
	 */
	public function delete ($model)
	{
		// Delete all rules first.
		CommerceDbHelper::beginStackedTransaction();
		try
		{

			$rules = craft()->commerce_shippingRules->getAllByMethodId($model->id);
			foreach ($rules as $rule)
			{
				craft()->commerce_shippingRules->deleteById($rule->id);
			}

			Commerce_ShippingMethodRecord::model()->deleteByPk($model->id);

			CommerceDbHelper::commitStackedTransaction();

			return true;
		}
		catch (\Exception $e)
		{
			CommerceDbHelper::rollbackStackedTransaction();

			return false;
		}

		CommerceDbHelper::rollbackStackedTransaction();

		return false;
	}
}