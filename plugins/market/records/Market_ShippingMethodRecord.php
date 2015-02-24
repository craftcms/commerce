<?php

namespace Craft;

/**
 * Class Market_ShippingMethodRecord
 *
 * @property int    $id
 * @property string $name
 * @property bool   $enabled
 * @package Craft
 */
class Market_ShippingMethodRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'market_shippingmethods';
	}

    public function defineIndexes()
    {
        return [
            ['columns' => ['name'], 'unique' => true],
        ];
    }

	protected function defineAttributes()
	{
		return [
			'name'      => [AttributeType::String, 'required' => true],
			'enabled'   => [AttributeType::Bool, 'required' => true, 'default' => 1],
		];
	}
}