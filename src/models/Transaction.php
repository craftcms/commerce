<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\gateways\BaseGateway;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use Omnipay\Common\Exception\OmnipayException;
use OpenCloud\Common\Base;

/**
 * Class Transaction
 *
 * @property \craft\commerce\models\Transaction       $parent
 * @property \craft\commerce\models\BasePaymentMethod $paymentMethod
 * @property \craft\commerce\elements\Order           $order
 * @property \craft\elements\User                     $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Transaction extends Model
{
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
     * @var BaseGateway
     */
    private $_gateway;

    /**
     * @var
     */
    private $_parentTransaction;


    /**
     * @param null $attributes
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
    public function canCapture()
    {
        // can only capture authorize payments
        if ($this->type != TransactionRecord::TYPE_AUTHORIZE || $this->status != TransactionRecord::STATUS_SUCCESS) {
            return false;
        }

        // check gateway supports capture
        try {
            $gateway = $this->paymentMethod->getGateway();
            if (!$gateway || !$gateway->supportsCapture()) {
                return false;
            }
        } catch (OmnipayException  $e) {
            return false;
        }

        // check transaction hasn't already been captured
        $exists = TransactionRecord::find()->where(['type' => ':type', 'status' => ':status', 'orderId' => ':orderId'], [
            ':type' => TransactionRecord::TYPE_CAPTURE,
            ':status' => TransactionRecord::STATUS_SUCCESS,
            ':orderId' => $this->orderId
        ])->exists();

        return !$exists;
    }

    /**
     * @return bool
     */
    public function canRefund()
    {
        // can only refund purchase or capture transactions
        $noRefundTransactions = [TransactionRecord::TYPE_PURCHASE, TransactionRecord::TYPE_CAPTURE];
        if (!in_array($this->type, $noRefundTransactions) || $this->status != TransactionRecord::STATUS_SUCCESS) {
            return false;
        }

        // check gateway supports refund
        try {
            $gateway = $this->paymentMethod->getGateway();
            $supportsRefund = $gateway->supportsRefund();
            if (!$gateway || !$supportsRefund) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been refunded
        $exists = TransactionRecord::find()->where(['type' => ':type', 'status' => ':status', 'orderId' => ':orderId'], [
            ':type' => TransactionRecord::TYPE_REFUND,
            ':status' => TransactionRecord::STATUS_SUCCESS,
            ':orderId' => $this->orderId
        ])->exists();

        return !$exists;
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
        return Plugin::geInstance()->getOrders()->getOrderById($this->orderId);
    }

    /**
     * @return BaseGateway|null
     */
    public function getGateway()
    {
        if (null === $this->_gateway) {
            $this->_gateway = Plugin::getInstance()->getGateways()->getGatewayById($this->gatewayId);
        }

        return $this->_gateway;
    }

    /**
     * @param BaseGateway $gateway
     *
     * @return void
     */
    public function setGateway(BaseGateway $gateway)
    {
        $this->_gateway = $gateway;
    }

}
