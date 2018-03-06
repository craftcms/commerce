<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Order status email record.
 *
 * @property Email $email
 * @property int $emailId
 * @property OrderStatus $orderStatus
 * @property int $orderStatusId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatusEmail extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_orderstatus_emails}}';
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
