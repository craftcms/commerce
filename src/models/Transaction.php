<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Gateway;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use DateTime;

/**
 * Class Transaction
 *
 * @property array|Transaction[] $childTransactions child transactions
 * @property Gateway $gateway
 * @property Order $order
 * @property Transaction $parent
 * @property-read float $refundableAmount
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Transaction extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Parent transaction ID
     */
    public $parentId;

    /**
     * @var int User ID
     */
    public $userId;

    /**
     * @var string Hash
     */
    public $hash;

    /**
     * @var int Gateway ID
     */
    public $gatewayId;

    /**
     * @var string Currency
     */
    public $currency;

    /**
     * @var float Payment Amount
     */
    public $paymentAmount;

    /**
     * @var string Payment currency
     */
    public $paymentCurrency;

    /**
     * @var float Payment Rate
     */
    public $paymentRate;

    /**
     * @var string Transaction Type
     */
    public $type;

    /**
     * @var float Amount
     */
    public $amount;

    /**
     * @var string Status
     */
    public $status;

    /**
     * @var string reference
     */
    public $reference;

    /**
     * @var string Code
     */
    public $code;

    /**
     * @var string Message
     */
    public $message;

    /**
     * @var string Note
     */
    public $note = '';

    /**
     * @var Mixed Response
     */
    public $response;

    /**
     * @var DateTime|null The date that the transaction was created
     */
    public $dateCreated;

    /**
     * @var DateTime|null The date that the transaction was last updated
     */
    public $dateUpdated;

    /**
     * @var Gateway
     */
    private $_gateway;

    /**
     * @var
     */
    private $_parentTransaction;

    /**
     * @var Order
     */
    private $_order;

    /**
     * @var Transaction[]
     */
    private $_children;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct($attributes = null)
    {
        // generate unique hash
        $this->hash = md5(uniqid(mt_rand(), true));

        parent::__construct($attributes);
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();
        ArrayHelper::removeValue($names, 'response');
        return $names;
    }

    /**
     * @inheritDoc
     */
    public function extraFields()
    {
        return [
            'response',
        ];
    }

    /**
     * @return bool
     */
    public function canCapture(): bool
    {
        return Plugin::getInstance()->getTransactions()->canCaptureTransaction($this);
    }

    /**
     * @return bool
     */
    public function canRefund(): bool
    {
        return Plugin::getInstance()->getTransactions()->canRefundTransaction($this);
    }

    /**
     * @return float
     */
    public function getRefundableAmount(): float
    {
        return Plugin::getInstance()->getTransactions()->refundableAmountForTransaction($this);
    }

    /**
     * @return Transaction|null
     */
    public function getParent()
    {
        if (null === $this->_parentTransaction) {
            $this->_parentTransaction = Plugin::getInstance()->getTransactions()->getTransactionById($this->parentId);
        }

        return $this->_parentTransaction;
    }

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        if (null === $this->_order) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order)
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    /**
     * @return Gateway|null
     */
    public function getGateway()
    {
        if (null === $this->_gateway && $this->gatewayId) {
            $this->_gateway = Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    /**
     * @param Gateway $gateway
     */
    public function setGateway(Gateway $gateway)
    {
        $this->_gateway = $gateway;
    }

    /**
     * Returns child transactions.
     *
     * @return Transaction[]
     */
    public function getChildTransactions(): array
    {
        if ($this->_children === null) {
            $this->_children = Plugin::getInstance()->getTransactions()->getChildrenByTransactionId($this->id);
        }

        return $this->_children;
    }

    /**
     * Adds a child transaction.
     *
     * @param Transaction $transaction
     */
    public function addChildTransaction(Transaction $transaction)
    {
        if ($this->_children === null) {
            $this->_children = [];
        }

        $this->_children[] = $transaction;
    }

    /**
     * Sets child transactions.
     *
     * @param array $transactions
     */
    public function setChildTransactions(array $transactions)
    {
        $this->_children = $transactions;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'status', 'orderId'], 'required']
        ];
    }
}
