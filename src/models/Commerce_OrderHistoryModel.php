<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Class Commerce_OrderHistoryModel
 *
 * @property int $id
 * @property string $message
 *
 * @property int $orderId
 * @property int $prevStatusId
 * @property int $newStatusId
 * @property int $customerId
 *
 * @property Commerce_OrderModel $order
 * @property Commerce_OrderStatusModel $prevStatus
 * @property Commerce_OrderStatusModel $newStatus
 * @property Commerce_CustomerModel $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderHistoryModel extends BaseModel
{
    use Commerce_ModelRelationsTrait;

    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'message' => [AttributeType::String],
            'orderId' => [AttributeType::Number],
            'prevStatusId' => [AttributeType::Number],
            'newStatusId' => [AttributeType::Number],
            'customerId' => [AttributeType::Number],
        ];
    }
}