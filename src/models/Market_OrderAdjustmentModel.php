<?php

namespace Craft;

/**
 * Class Market_OrderAdjustmentModel
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
class Market_OrderAdjustmentModel extends BaseModel
{
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