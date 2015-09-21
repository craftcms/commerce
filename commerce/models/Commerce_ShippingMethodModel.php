<?php

namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_ShippingMethodModel
 *
 * @property int                        $id
 * @property string                     $name
 * @property bool                       $enabled
 * @property bool                       $default
 * @property Commerce_ShippingRuleModel[] rules
 * @package Craft
 */
class Commerce_ShippingMethodModel extends BaseModel
{
	use Commerce_ModelRelationsTrait;

	/**
	 * @return Commerce_ShippingRuleModel[]
	 */
	public function getRules ()
	{
		return craft()->commerce_shippingRule->getAllByMethodId($this->id);
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