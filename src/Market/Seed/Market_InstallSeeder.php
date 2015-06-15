<?php

namespace Market\Seed;

use Craft\Market_OrderTypeModel;
use Craft\Market_ShippingMethodRecord;
use Craft\Market_OrderStatusModel;
/**
 * Default Seeder
 */
class Market_InstallSeeder implements Market_SeederInterface
{

	public function seed()
	{
		$this->defaultShippingMethod();
		$this->defaultOrderTypes();
	}

	/**
	 * Shipping Methods
	 */
	private function defaultShippingMethod()
	{
		$method          = new Market_ShippingMethodRecord();
		$method->name    = 'Default Shipping Method';
		$method->enabled = true;
		$method->save();
	}

	/**
	 * @throws \Exception
	 */
	private function defaultOrderTypes()
	{

		$types = ['Order'];

		$shippingMethod = Market_ShippingMethodRecord::model()->find();

		foreach ($types as $type) {
			$orderType                   = new Market_OrderTypeModel;
			$orderType->name             = ucwords($type);
			$orderType->handle           = $type;
			$orderType->shippingMethodId = $shippingMethod->id;

			// Set the field layout
			$fieldLayout       = \Craft\craft()->fields->assembleLayout([], []);
			$fieldLayout->type = 'Market_Order';
			$orderType->setFieldLayout($fieldLayout);

			$data  = [
				'name'        => 'New',
				'orderTypeId' => '1',
				'handle'      => 'new',
				'color'       => '#31FF79',
				'default'     => true
			];

			$state = Market_OrderStatusModel::populateModel($data);

			\Craft\craft()->market_orderType->save($orderType);

			\Craft\craft()->market_orderStatus->save($state,[]);

        }

	}
}