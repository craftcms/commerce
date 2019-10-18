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
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TRANSACTIONS;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getParent(): ActiveQueryInterface
    {
        return $this->hasOne(self::class, ['id' => 'parentId']);
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
