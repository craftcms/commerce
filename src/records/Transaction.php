<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * Transaction record.
 *
 * @property int         $id
 * @property string      $hash
 * @property string      $type
 * @property float       $amount
 * @property string      $status
 * @property string      $reference
 * @property string      $message
 * @property string      $response
 * @property string      $code
 * @property int         $parentId
 * @property int         $userId
 * @property int         $gatewayId
 * @property int         $orderId
 * @property Transaction $parent
 * @property Gateway     $gateway
 * @property Order       $order
 * @property User        $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Transaction extends ActiveRecord
{
    // Constants
    // =========================================================================

    const TYPE_AUTHORIZE = 'authorize';
    const TYPE_CAPTURE = 'capture';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_REFUND = 'refund';
    const STATUS_PENDING = 'pending';
    const STATUS_REDIRECT = 'redirect';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    // Properties
    // =========================================================================

    /**
     * @var int $total
     */
    public $total = 0;

    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_transactions}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getParent(): ActiveQueryInterface
    {
        return $this->hasOne(Transaction::class, ['id' => 'parentId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['id' => 'gatewayId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
