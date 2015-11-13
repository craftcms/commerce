<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;
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
 * @property string $status
 * @property string $reference
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
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_TransactionModel extends BaseModel
{
    use Commerce_ModelRelationsTrait;

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
        if ($this->type != Commerce_TransactionRecord::AUTHORIZE || $this->status != Commerce_TransactionRecord::SUCCESS) {
            return false;
        }

        // check gateway supports capture
        try {
            $adapter = $this->paymentMethod->getGatewayAdapter();
            if (!$adapter || !$adapter->getGateway()->supportsCapture()) {
                return false;
            }
        } catch (OmnipayException  $e) {
            return false;
        }

        // check transaction hasn't already been captured
        $criteria = [
            'condition' => 'type = ? AND status = ? AND orderId = ?',
            'params' => [
                Commerce_TransactionRecord::CAPTURE,
                Commerce_TransactionRecord::SUCCESS,
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
        if (!in_array($this->type, [
                Commerce_TransactionRecord::PURCHASE,
                Commerce_TransactionRecord::CAPTURE
            ]) || $this->status != Commerce_TransactionRecord::SUCCESS
        ) {
            return false;
        }

        // check gateway supports refund
        try {
            $adapter = $this->paymentMethod->getGatewayAdapter();
            if (!$adapter || !$adapter->getGateway()->supportsRefund()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been refunded
        $criteria = [
            'condition' => 'type = ? AND status = ? AND orderId = ?',
            'params' => [
                Commerce_TransactionRecord::REFUND,
                Commerce_TransactionRecord::SUCCESS,
                $this->orderId
            ],
        ];
        $exists = craft()->commerce_transactions->transactionExists($criteria);

        return !$exists;
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
            'type' => AttributeType::String,
            'amount' => AttributeType::Number,
            'status' => AttributeType::String,
            'reference' => AttributeType::String,
            'message' => AttributeType::String,
            'response' => AttributeType::String,
        ];
    }
}
