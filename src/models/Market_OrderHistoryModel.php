<?php

namespace Craft;

use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_OrderHistoryModel
 *
 * @property int                     id
 * @property string                  message
 *
 * @property int                     orderId
 * @property int                     prevStatusId
 * @property int                     newStatusId
 * @property int                     customerId
 *
 * @property Market_OrderModel       order
 * @property Market_OrderStatusModel prevStatus
 * @property Market_OrderStatusModel newStatus
 * @property Market_CustomerModel    customer
 *
 * @package Craft
 */
class Market_OrderHistoryModel extends BaseModel
{
    use Market_ModelRelationsTrait;

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('market/settings/orderhistories/' . $this->id);
    }

    protected function defineAttributes()
    {
        return [
            'id'           => AttributeType::Number,
            'message'      => [AttributeType::String],
            'orderId'      => [AttributeType::Number],
            'prevStatusId' => [AttributeType::Number],
            'newStatusId'  => [AttributeType::Number],
            'customerId'   => [AttributeType::Number],
        ];
    }
}