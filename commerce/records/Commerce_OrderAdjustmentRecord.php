<?php
namespace Craft;

/**
 * Order adjustment record.
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $type
 * @property float $amount
 * @property bool $included
 * @property string $optionsJson
 * @property int $orderId
 *
 * @property Commerce_OrderRecord $order
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_OrderAdjustmentRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_orderadjustments';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['orderId']],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'order' => [
                self::BELONGS_TO,
                'Commerce_OrderRecord',
                'required' => true,
                'onDelete' => static::CASCADE
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'type' => [AttributeType::String, 'required' => true],
            'name' => [AttributeType::String],
            'description' => [AttributeType::String],
            'amount' => [
                AttributeType::Number,
                'required' => true,
                'decimals' => 4
            ],
            'included' => AttributeType::Bool,
            'optionsJson' => [AttributeType::Mixed, 'required' => true],
            'orderId' => [AttributeType::Number, 'required' => true],
        ];
    }
}