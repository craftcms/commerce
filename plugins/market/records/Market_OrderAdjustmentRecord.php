<?php

namespace Craft;

/**
 * Class Market_OrderAdjustmentRecord
 *
 * @property int    $id
 * @property string $name
 * @property string $type
 * @property float  $rate
 * @property float  $amount
 * @property bool   $include
 * @property int    $orderId
 *
 * @property Market_OrderRecord $order
 * @package Craft
 */
class Market_OrderAdjustmentRecord extends BaseRecord
{

	public function getTableName()
	{
		return 'market_orderadjustments';
	}

	public function defineIndexes()
	{
		return [
			['columns' => ['orderId']],
		];
	}

    public function defineRelations()
    {
        return [
            'order' => [self::BELONGS_TO, 'Market_OrderRecord', 'required' => true],
        ];
    }

    protected function defineAttributes()
	{
		return [
			'type'    => [AttributeType::String, 'required' => true],
			'name'    => [AttributeType::String],
            'rate'    => [AttributeType::Number, 'required' => true, 'decimals' => 5],
            'amount'  => [AttributeType::Number, 'required' => true, 'decimals' => 5],
			'include' => [AttributeType::Bool, 'required' => true, 'default' => 0],
            'orderId' => [AttributeType::Number, 'required' => true],
		];
	}
}