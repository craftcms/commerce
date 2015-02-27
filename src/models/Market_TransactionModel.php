<?php

namespace Craft;
use Market\Traits\Market_ModelRelationsTrait;
use Omnipay\Common\Exception\OmnipayException;

/**
 * Class Market_TransactionModel
 *
 * @package Craft
 *
 * @property int    $id
 * @property string $hash
 * @property string $type
 * @property float  $amount
 * @property string status
 * @property string reference
 * @property string message
 * @property string response
 *
 * @property int    parentId
 * @property int    userId
 * @property int    paymentMethodId
 * @property int    orderId
 *
 * @property Market_TransactionModel   parent
 * @property Market_PaymentMethodModel paymentMethod
 * @property Market_OrderModel         order
 */
class Market_TransactionModel extends BaseModel
{
    use Market_ModelRelationsTrait;

	public function __construct($attributes = NULL)
	{
		// generate unique hash
		$this->hash = md5(uniqid(mt_rand(), true));

		parent::__construct($attributes);
	}

	protected function defineAttributes()
	{
        return [
            'id'              => AttributeType::Number,
            'orderId'         => AttributeType::Number,
            'hash'            => AttributeType::String,
            'paymentMethodId' => AttributeType::Number,
            'type'            => AttributeType::String,
            'amount'          => AttributeType::Number,
            'status'          => AttributeType::String,
            'reference'       => AttributeType::String,
            'message'         => AttributeType::String,
            'response'        => AttributeType::String,
        ];
    }

    /**
     * @return bool
     */
    public function canCapture()
    {
        // can only capture authorize payments
        if ($this->type != Market_TransactionRecord::AUTHORIZE || $this->status != Market_TransactionRecord::SUCCESS) {
            return false;
        }

        // check gateway supports capture
        try {
            $gateway = craft()->market_gateway->getGateway($this->paymentMethod->class);
            if (!$gateway || !$gateway->supportsCapture()) {
                return false;
            }
        } catch (OmnipayException  $e) {
            return false;
        }

        // check transaction hasn't already been refunded
        $condition = 'type = ? AND status = ? AND orderId = ?';
        $params    = [Market_TransactionRecord::CAPTURE, Market_TransactionRecord::SUCCESS, $this->orderId];
        $exists    = Market_TransactionRecord::model()->exists($condition, $params);

        return !$exists;
    }


    public function canRefund()
    {
        // can only refund purchase or capture transactions
        if (!in_array($this->type, [Market_TransactionRecord::PURCHASE, Market_TransactionRecord::CAPTURE]) || $this->status != Market_TransactionRecord::SUCCESS) {
            return false;
        }

        // check gateway supports refund
        try {
            $gateway = craft()->market_gateway->getGateway($this->paymentMethod->class);
            if (!$gateway || !$gateway->supportsRefund()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been refunded
        $condition = 'type = ? AND status = ? AND orderId = ?';
        $params    = [Market_TransactionRecord::REFUND, Market_TransactionRecord::SUCCESS, $this->orderId];
        $exists    = Market_TransactionRecord::model()->exists($condition, $params);

        return !$exists;
    }
}
