<?php
namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_ShippingMethodService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_ShippingMethodService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_ShippingMethodModel
	 */
	public function getById ($id)
	{
		$record = Market_ShippingMethodRecord::model()->findById($id);

		return Market_ShippingMethodModel::populateModel($record);
	}

	/**
	 * Gets the default method or first available if no default set.
	 */
	public function getDefault ()
	{
		$method = Market_ShippingMethodRecord::model()->findByAttributes(['default' => true]);
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
	 * @return Market_ShippingMethodModel[]
	 */
	public function getAll ($criteria = [])
	{
		$records = Market_ShippingMethodRecord::model()->findAll($criteria);

		return Market_ShippingMethodModel::populateModels($records);
	}

	/**
	 * @return bool
	 */
	public function exists ()
	{
		return Market_ShippingMethodRecord::model()->exists();
	}

	/**
	 * @param Market_OrderModel $cart
	 *
	 * @return array
	 */
	public function calculateForCart (Market_OrderModel $cart)
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
	 * @param Market_OrderModel          $order
	 * @param Market_ShippingMethodModel $method
	 *
	 * @return bool|Market_ShippingRuleModel
	 */
	public function getMatchingRule (
		Market_OrderModel $order,
		Market_ShippingMethodModel $method
	)
	{
		foreach ($method->rules as $rule)
		{
			if (craft()->market_shippingRule->matchOrder($rule, $order))
			{
				return $rule;
			}
		}

		return false;
	}

	/**
	 * @param Market_ShippingMethodModel $model
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save (Market_ShippingMethodModel $model)
	{
		if ($model->id)
		{
			$record = Market_ShippingMethodRecord::model()->findById($model->id);

			if (!$record)
			{
				throw new Exception(Craft::t('No shipping method exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Market_ShippingMethodRecord();
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
				Market_ShippingMethodRecord::model()->updateAll(['default' => 0],
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
		MarketDbHelper::beginStackedTransaction();
		try
		{

			$rules = craft()->market_shippingRule->getAllByMethodId($model->id);
			foreach ($rules as $rule)
			{
				craft()->market_shippingRule->deleteById($rule->id);
			}

			Market_ShippingMethodRecord::model()->deleteByPk($model->id);

			MarketDbHelper::commitStackedTransaction();

			return true;
		}
		catch (\Exception $e)
		{
			MarketDbHelper::rollbackStackedTransaction();

			return false;
		}

		MarketDbHelper::rollbackStackedTransaction();

		return false;
	}
}