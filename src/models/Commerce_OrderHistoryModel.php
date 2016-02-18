<?php
namespace Craft;

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
 * @property DateTime $dateCreated
 *
 * @property Commerce_OrderModel $order
 * @property Commerce_OrderStatusModel $prevStatus
 * @property Commerce_OrderStatusModel $newStatus
 * @property Commerce_CustomerModel $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_OrderHistoryModel extends BaseModel
{

    /**
     * @return Commerce_OrderModel|null
     */
    public function getOrder()
    {
        return craft()->commerce_orders->getOrderById($this->orderId);
    }

    /**
     * @return Commerce_OrderStatusModel|null
     */
    public function getPrevStatus()
    {
        return craft()->commerce_orderStatuses->getOrderStatusById($this->prevStatusId);
    }

    /**
     * @return Commerce_OrderStatusModel|null
     */
    public function getNewStatus()
    {
        return craft()->commerce_orderStatuses->getOrderStatusById($this->newStatusId);
    }

    /**
     * @return Commerce_CustomerModel|null
     */
    public function getCustomer()
    {
        return craft()->commerce_customers->getCustomerById($this->customerId);
    }

    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'message' => [AttributeType::String],
            'orderId' => [AttributeType::Number],
            'prevStatusId' => [AttributeType::Number],
            'newStatusId' => [AttributeType::Number],
            'customerId' => [AttributeType::Number],
            'dateCreated' => [AttributeType::DateTime]
        ];
    }
}





