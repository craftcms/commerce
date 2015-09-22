<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Shipping method model.
 *
 * @property int                          $id
 * @property string                       $name
 * @property bool                         $enabled
 * @property bool                         $default
 * @property Commerce_ShippingRuleModel[] $rules
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
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