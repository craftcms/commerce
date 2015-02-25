<?php

namespace Craft;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderAdjustmentModel
 *
 * @property int    $id
 * @property string $name
 * @property string description
 * @property string $type
 * @property float  $amount
 * @property string optionsJson
 * @property int    $orderId
 *
 * @property Market_OrderRecord $order
 * @package Craft
 */
class Market_OrderAdjustmentModel extends BaseModel
{
    use Market_ModelRelationsTrait;

    protected function defineAttributes()
    {
        return [
            'id'      => AttributeType::Number,
            'type'        => [AttributeType::String, 'required' => true],
            'name'        => [AttributeType::String],
            'description' => [AttributeType::String],
            'amount'      => [AttributeType::Number, 'required' => true, 'decimals' => 5],
            'optionsJson' => [AttributeType::Mixed, 'required' => true],
            'orderId'     => [AttributeType::Number, 'required' => true],
        ];
    }
}