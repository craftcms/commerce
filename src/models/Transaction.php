<?php

namespace craft\commerce\models;

use craft\commerce\base\Gateway;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\web\User;
use DateTime;

/**
 * Class Transaction
 *
 * @property Transaction         $parent
 * @property Gateway             $gateway
 * @property Order               $order
 * @property array|Transaction[] $childTransactions
 * @property User                $user
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
     * Return child transactions.
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
     * Add a child transaction.
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
     * Set child transactions.
     *
     * @param array $transactions
     */
    public function setChildTransactions(array $transactions)
    {
        $this->_children = $transactions;
    }
}
