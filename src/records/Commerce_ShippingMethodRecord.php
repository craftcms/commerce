<?php

namespace Craft;

/**
 * Class Commerce_ShippingMethodRecord
 *
 * @property int                         $id
 * @property string                      $name
 * @property bool                        $enabled
 * @property bool                        $default
 *
 * @property Commerce_ShippingRuleRecord[] rules
 * @package Craft
 */
class Commerce_ShippingMethodRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_shippingmethods';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['name'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function scopes ()
	{
		return [
			'enabled' => [
				'condition' => 'enabled = 1',
			],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'rules' => [
				self::HAS_MANY,
				'Commerce_ShippingRuleRecord',
				'methodId',
				'order' => 'rules.priority'
			],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
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