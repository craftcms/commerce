<?php
namespace Craft;

use Omnipay\Common\Exception\OmnipayException;

/**
 * Class Stripey_TransactionRecord
 * @package Craft
 *
 * @property int $id
 * @property string $hash
 * @property string $type
 * @property float $amount
 * @property string status
 * @property string reference
 * @property string message
 * @property string response
 *
 * @property int parentId
 * @property int userId
 * @property int paymentMethodId
 * @property int orderId
 *
 * @property Stripey_TransactionRecord $parent
 * @property Stripey_PaymentMethodRecord paymentMethod
 * @property UserRecord $user
 */
class Stripey_TransactionRecord extends BaseRecord
{
    const AUTHORIZE = 'authorize';
    const CAPTURE = 'capture';
    const PURCHASE = 'purchase';
    const REFUND = 'refund';

    const PENDING = 'pending';
    const REDIRECT = 'redirect';
    const SUCCESS = 'success';
    const FAILED = 'failed';

    public function getTableName()
    {
        return 'stripey_transactions';
    }

    protected function defineAttributes()
    {
        return array(
            'hash'              => array(AttributeType::String, 'maxLength' => 32),
            'type'              => array(AttributeType::Enum, 'values' => array(self::AUTHORIZE, self::CAPTURE, self::PURCHASE, self::REFUND), 'required' => true),
            'amount'            => array(AttributeType::Number, 'min' => -1000000000000, 'max' => 1000000000000, 'decimals' => 2),
            'status'            => array(AttributeType::Enum, 'values' => array(self::PENDING, self::REDIRECT, self::SUCCESS, self::FAILED), 'required' => true),
            'reference'         => array(AttributeType::String),
            'message'           => array(AttributeType::Mixed),
            'response'          => array(AttributeType::Mixed),

            'orderId' => array(AttributeType::Number, 'required' => true), //should be replaced with relation when the Order record will be ready
        );
    }

    public function defineRelations()
    {
        return array(
            'parent'        => array(self::BELONGS_TO, 'Stripey_TransactionRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE),
            'user'          => array(self::BELONGS_TO, 'UserRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE),
            'paymentMethod' => array(self::BELONGS_TO, 'Stripey_PaymentMethodRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE),
//            'order' => array(self::BELONGS_TO, 'Cellar_OrderRecord', 'orderId', 'required' => true, 'onDelete' => self::CASCADE),
        );
    }

    /**
     * @return bool
     */
    public function canCapture()
    {
        // can only capture authorize payments
        if ($this->type != self::AUTHORIZE || $this->status != self::SUCCESS) {
            return false;
        }

        // check gateway supports capture
        try {
            $gateway = craft()->stripey_gateway->getGateway($this->paymentMethod->class);
            if (!$gateway || !$gateway->supportsCapture()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been refunded
        $condition = 'type = ? AND status = ? AND orderId = ?';
        $params = array(self::CAPTURE, self::SUCCESS, $this->orderId);
        $exists = Stripey_TransactionRecord::model()->exists($condition, $params);

        return !$exists;
    }


    public function canRefund()
    {
        // can only refund purchase or capture transactions
        if (!in_array($this->type, array(self::PURCHASE, self::CAPTURE)) || $this->status != self::SUCCESS) {
            return false;
        }

        // check gateway supports refund
        try {
            $gateway = craft()->stripey_gateway->getGateway($this->paymentMethod->class);
            if (!$gateway || !$gateway->supportsRefund()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been refunded
        $condition = 'type = ? AND status = ? AND orderId = ?';
        $params = array(self::REFUND, self::SUCCESS, $this->orderId);
        $exists = Stripey_TransactionRecord::model()->exists($condition, $params);

        return !$exists;
    }
}
