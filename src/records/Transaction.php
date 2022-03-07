<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * Transaction record.
 *
 * @property float $amount
 * @property string $code
 * @property Gateway $gateway
 * @property int $gatewayId
 * @property string $hash
 * @property int $id
 * @property string $message
 * @property Order $order
 * @property int $orderId
 * @property Transaction $parent
 * @property int $parentId
 * @property string $reference
 * @property string $response
 * @property string $status
 * @property string $type
 * @property User $user
 * @property int $userId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Transaction extends ActiveRecord
{
    public const TYPE_AUTHORIZE = 'authorize';
    public const TYPE_CAPTURE = 'capture';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_REFUND = 'refund';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REDIRECT = 'redirect';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';


    /**
     * @var int $total
     */
    public int $total = 0;


    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TRANSACTIONS;
    }

    public function getParent(): ActiveQueryInterface
    {
        return $this->hasOne(self::class, ['id' => 'parentId']);
    }

    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['id' => 'gatewayId']);
    }

    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }

    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
