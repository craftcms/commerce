<?php
namespace Craft;

/**
 * Order status email record.
 *
 * @property int $orderStatusId
 * @property int $emailId
 *
 * @property Commerce_OrderStatusRecord $orderStatus
 * @property Commerce_EmailRecord $email
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_OrderStatusEmailRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return "commerce_orderstatus_emails";
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'orderStatus' => [
                static::BELONGS_TO,
                'Commerce_OrderStatusRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
            'email' => [
                static::BELONGS_TO,
                'Commerce_EmailRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
        ];
    }

}