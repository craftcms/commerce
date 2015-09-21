<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_ShippingMethodModel
 *
 * @property int                        $id
 * @property string                     $name
 * @property bool                       $enabled
 * @property bool                       $default
 * @property Market_ShippingRuleModel[] rules
 * @package Craft
 */
class Market_ShippingMethodModel extends BaseModel
{
	use Market_ModelRelationsTrait;

	/**
	 * @return Market_ShippingRuleModel[]
	 */
	public function getRules ()
	{
		return craft()->market_shippingRule->getAllByMethodId($this->id);
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'id'      => AttributeType::Number,
			'name'    => [AttributeType::String, 'required' => true],
			'enabled' => [
				AttributeType::Bool,
				'required' => true,
				'default'  => 1
			],
			'default' => [
				AttributeType::Bool,
				'required' => true,
				'default'  => 0
			],
		];
	}
}