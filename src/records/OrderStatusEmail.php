<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Order status email record.
 *
 * @property int         $orderStatusId
 * @property int         $emailId
 *
 * @property OrderStatus $orderStatus
 * @property Email       $email
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class OrderStatusEmail extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%"commerce_orderstatus_emails}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrderStatus(): ActiveQueryInterface
    {
        return $this->hasOne(OrderStatus::class, ['id' => 'orderStatusId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getEmail(): ActiveQueryInterface
    {
        return $this->hasOne(Email::class, ['id' => 'emailId']);
    }
}