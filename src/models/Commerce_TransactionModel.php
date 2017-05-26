<?php
namespace Craft;

use Omnipay\Common\Exception\OmnipayException;

/**
 * Class Commerce_TransactionModel
 *
 * @package   Craft
 *
 * @property int $id
 * @property string $hash
 * @property string $type
 * @property float $amount
 * @property float $paymentAmount
 * @property string $currency
 * @property string $paymentCurrency
 * @property float $paymentRate
 * @property string $status
 * @property string $reference
 * @property string $code
 * @property string $message
 * @property string $response
 *
 * @property int $parentId
 * @property int $userId
 * @property int $paymentMethodId
 * @property int $orderId
 *
 * @property Commerce_TransactionModel $parent
 * @property Commerce_PaymentMethodModel $paymentMethod
 * @property Commerce_OrderModel $order
 * @property UserModel $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_TransactionModel extends BaseModel
{
    /*
     * @var
     */
    private $_paymentMethod;

    /*
     * @var
     */
    private $_parentTransaction;

    /*
     * @var Commerce_OrderModel|null
     */
    private $_order;

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
        if ($this->type != Commerce_TransactionRecord::TYPE_AUTHORIZE || $this->status != Commerce_TransactionRecord::STATUS_SUCCESS) {
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
        $criteria = [
            'condition' => 'type = ? AND status = ? AND orderId = ?',
            'params' => [
                Commerce_TransactionRecord::TYPE_CAPTURE,
                Commerce_TransactionRecord::STATUS_SUCCESS,
                $this->orderId
            ],
        ];
        $exists = craft()->commerce_transactions->transactionExists($criteria);

        return !$exists;
    }

    /**
     * @return bool
     */
    public function canRefund()
    {
        // can only refund purchase or capture transactions
        $noRefundTransactions = [Commerce_TransactionRecord::TYPE_PURCHASE, Commerce_TransactionRecord::TYPE_CAPTURE];
        if (!in_array($this->type, $noRefundTransactions) || $this->status != Commerce_TransactionRecord::STATUS_SUCCESS) {
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
        $criteria = [
            'condition' => 'type = ? AND status = ? AND orderId = ?',
            'params' => [
                Commerce_TransactionRecord::TYPE_REFUND,
                Commerce_TransactionRecord::STATUS_SUCCESS,
                $this->orderId
            ],
        ];
        $exists = craft()->commerce_transactions->transactionExists($criteria);

        return !$exists;
    }

    /**
     * @return Commerce_TransactionModel|null
     */
    public function getParent()
    {
        if (!isset($this->_parentTransaction))
        {
            $this->_parentTransaction = craft()->commerce_transactions->getTransactionById($this->parentId);
        }

        return $this->_parentTransaction;
    }

    /**
     * @return Commerce_OrderModel|null
     */
    public function getOrder()
    {
        if ($this->_order === null)
        {
            $this->_order = craft()->commerce_orders->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Commerce_OrderModel $order
     */
    public function setOrder(Commerce_OrderModel $order)
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    /**
     * @return Commerce_PaymentMethodModel|null
     */
    public function getPaymentMethod()
    {
        if (!isset($this->_paymentMethod))
        {
            $this->_paymentMethod = craft()->commerce_paymentMethods->getPaymentMethodById($this->paymentMethodId);
        }

        return $this->_paymentMethod;
    }

    /**
     * @param $paymentMethod Commerce_PaymentMethodModel
     *
     * @return void
     */
    public function setPaymentMethod(Commerce_PaymentMethodModel $paymentMethod)
    {
        $this->_paymentMethod = $paymentMethod;
    }


    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'orderId' => AttributeType::Number,
            'parentId' => AttributeType::Number,
            'userId' => AttributeType::Number,
            'hash' => AttributeType::String,
            'paymentMethodId' => AttributeType::Number,
            'currency' => AttributeType::String,
            'paymentAmount' => AttributeType::Number,
            'paymentCurrency' => AttributeType::String,
            'paymentRate'=> AttributeType::Number,
            'type' => AttributeType::String,
            'amount' => AttributeType::Number,
            'status' => AttributeType::String,
            'reference' => AttributeType::String,
            'code' => AttributeType::String,
            'message' => AttributeType::String,
            'response' => AttributeType::String,
            'dateUpdated' => AttributeType::String,
        ];
    }
}
