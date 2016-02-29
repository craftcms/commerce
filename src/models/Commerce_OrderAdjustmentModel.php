<?php
namespace Craft;

/**
 * Order adjustment model.
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
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderAdjustmentModel extends BaseModel
{

    /**
     * @return Commerce_OrderModel|null
     */
    public function getOrder()
    {
        return craft()->commerce_orders->getOrderById($this->id);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'type' => [AttributeType::String, 'required' => true],
            'name' => [AttributeType::String],
            'description' => [AttributeType::String],
            'amount' => [
                AttributeType::Number,
                'required' => true,
                'decimals' => 4
            ],
            'included' => [AttributeType::Bool, 'default' => false],
            'optionsJson' => [AttributeType::Mixed, 'required' => true],
            'orderId' => [AttributeType::Number, 'required' => true],
        ];
    }
}