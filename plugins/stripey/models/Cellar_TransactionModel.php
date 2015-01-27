<?php

namespace Craft;

require_once(CRAFT_PLUGINS_PATH . "cellar/vendor/autoload.php");

use Omnipay\Common\GatewayFactory;
use Omnipay\Common\Exception\OmnipayException;

class Cellar_TransactionModel extends BaseModel
{
    const AUTHORIZE = 'authorize';
    const CAPTURE = 'capture';
    const PURCHASE = 'purchase';
    const REFUND = 'refund';

    const PENDING = 'pending';
    const REDIRECT = 'redirect';
    const SUCCESS = 'success';
    const FAILED = 'failed';

    public function __construct($attributes = null)
    {
        // generate unique hash
        $this->hash = md5(uniqid(mt_rand(), true));

        parent::__construct($attributes);
    }

    protected function defineAttributes()
    {
        return array(

            'id' => AttributeType::Number,
            'userId' => AttributeType::Number,
            'orderId' => AttributeType::Number,
            'hash' => array(ColumnType::Varchar, 'maxLength' => 32),
            'payment_method' => array(AttributeType::String, 'required' => true),
            'type' => array(ColumnType::Varchar, 'maxLength' => 10),
            'amount' => array(AttributeType::Number, 'min' => -1000000000000, 'max' => 1000000000000, 'decimals' => 2),
            'status' => array(ColumnType::Varchar, 'maxLength' => 10),
            'reference' => array(AttributeType::String, 'required' => false),
            'message' => array(AttributeType::String, 'required' => false, 'column' => ColumnType::Text),
            'response' => array(AttributeType::String, 'required' => false, 'column' => ColumnType::Text),
            'dateCreated' => AttributeType::DateTime,
        );
    }

    public function getUser()
    {
        if ($this->userId) {
            return craft()->users->getUserById($this->userId);
        }
    }

    public function getOrder()
    {
        if ($this->orderId) {
            return craft()->cellar_orders->getOrder($this->orderId);
        }
    }

    public function canCapture()
    {
        // can only capture authorize payments
        if ($this->type != static::AUTHORIZE || $this->status != static::SUCCESS) {
            return false;
        }

        // check gateway supports capture
        try {
            if (!GatewayFactory::create($this->payment_method)->supportsCapture()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been captured

        $conditions = '
            type=:type and status=:status and orderId=:orderId
        ';

        $params = array(
            ':type' => static::CAPTURE,
            ':status' => static::SUCCESS,
            ':orderId' => $this->orderId
        );

        $records = Cellar_TransactionRecord::model()->find($conditions, $params);

        if (!$records) {
            return true;
        }

        return false;
    }

    public function canRefund()
    {
        // can only refund purchase or capture transactions
        if (!in_array($this->type, array(static::PURCHASE, static::CAPTURE)) ||
            $this->status != static::SUCCESS
        ) {
            return false;
        }

        // check gateway supports refund
        try {
            if (!GatewayFactory::create($this->payment_method)->supportsRefund()) {
                return false;
            }
        } catch (OmnipayException $e) {
            return false;
        }

        // check transaction hasn't already been refunded

        $conditions = '
            type=:type and status=:status and orderId=:orderId
        ';

        $params = array(
            ':type' => static::REFUND,
            ':status' => static::SUCCESS,
            ':orderId' => $this->orderId
        );

        $records = Cellar_TransactionRecord::model()->find($conditions, $params);

        if (!$records) {
            return true;
        }

        return false;
    }
}
