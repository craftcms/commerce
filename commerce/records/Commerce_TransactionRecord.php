<?php
namespace Craft;

/**
 * Transaction record.
 *
 * @property int $id
 * @property string $hash
 * @property string $type
 * @property float $amount
 * @property string $status
 * @property string $reference
 * @property string $message
 * @property string $response
 * @property string $code
 *
 * @property int $parentId
 * @property int $userId
 * @property int $paymentMethodId
 * @property int $orderId
 *
 * @property Commerce_TransactionRecord $parent
 * @property Commerce_PaymentMethodRecord $paymentMethod
 * @property Commerce_OrderRecord $order
 * @property UserRecord $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_TransactionRecord extends BaseRecord
{
    const TYPE_AUTHORIZE = 'authorize';
    const TYPE_CAPTURE = 'capture';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_REFUND = 'refund';

    const STATUS_PENDING = 'pending';
    const STATUS_REDIRECT = 'redirect';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    /* @var int $total */
    public $total = 0;
    /* @var array $types */
    private $types = [
        self::TYPE_AUTHORIZE,
        self::TYPE_CAPTURE,
        self::TYPE_PURCHASE,
        self::TYPE_REFUND
    ];

    /**
     * @var array
     */
    private $statuses = [
        self::STATUS_PENDING,
        self::STATUS_REDIRECT,
        self::STATUS_SUCCESS,
        self::STATUS_FAILED
    ];

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_transactions';
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'parent' => [
                self::BELONGS_TO,
                'Commerce_TransactionRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
            'paymentMethod' => [
                self::BELONGS_TO,
                'Commerce_PaymentMethodRecord',
                'onDelete' => self::RESTRICT,
                'onUpdate' => self::CASCADE
            ],
            'order' => [
                self::BELONGS_TO,
                'Commerce_OrderRecord',
                'required' => true,
                'onDelete' => self::CASCADE
            ],
            'user' => [
                self::BELONGS_TO,
                'UserRecord',
                'onDelete' => self::SET_NULL
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'hash' => [AttributeType::String, 'maxLength' => 32],
            'type' => [
                AttributeType::Enum,
                'values' => $this->types,
                'required' => true
            ],
            'amount' => [
                AttributeType::Number,
                'decimals' => 4
            ],
            'paymentAmount' => [
                AttributeType::Number,
                'decimals' => 4
            ],
            'currency' => AttributeType::String,
            'paymentCurrency' => AttributeType::String,
            'paymentRate'=> [
                AttributeType::Number,
                'decimals' => 4
            ],
            'status' => [
                AttributeType::Enum,
                'values' => $this->statuses,
                'required' => true
            ],
            'reference' => [AttributeType::String],
            'code' => [AttributeType::String],
            'message' => [AttributeType::Mixed], //TODO change to string
            'response' => [AttributeType::Mixed],
            'orderId' => [AttributeType::Number, 'required' => true],
        ];
    }
}
