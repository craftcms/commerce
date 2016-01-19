<?php
namespace Craft;

/**
 * Order hsitory record.
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
 * @property Commerce_OrderRecord $order
 * @property Commerce_OrderStatusRecord $prevStatus
 * @property Commerce_OrderStatusRecord $newStatus
 * @property Commerce_CustomerRecord $customer
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_OrderHistoryRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_orderhistories';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'order' => [
                static::BELONGS_TO,
                'Commerce_OrderRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
            'prevStatus' => [
                static::BELONGS_TO,
                'Commerce_OrderStatusRecord',
                'onDelete' => self::RESTRICT,
                'onUpdate' => self::CASCADE
            ],
            'newStatus' => [
                static::BELONGS_TO,
                'Commerce_OrderStatusRecord',
                'onDelete' => self::RESTRICT,
                'onUpdate' => self::CASCADE
            ],
            'customer' => [
                static::BELONGS_TO,
                'Commerce_CustomerRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'orderId' => [AttributeType::Number, 'required' => true],
            'customerId' => [AttributeType::Number, 'required' => true],
            'message' => [AttributeType::Mixed],
        ];
    }

}