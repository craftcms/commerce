<?php
namespace Craft;


require_once(CRAFT_PLUGINS_PATH . "cellar/vendor/autoload.php");

use Omnipay\Common\GatewayFactory;

class Cellar_TransactionRecord extends BaseRecord
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
        return 'cellar_transactions';
    }

    protected function defineAttributes()
    {
        return array(
            'hash' => array(ColumnType::Varchar, 'maxLength' => 32),
            'payment_method' => array(AttributeType::String, 'required' => true),
            'type' => array(ColumnType::Varchar, 'maxLength' => 10),
            'amount' => array(AttributeType::Number, 'min' => -1000000000000, 'max' => 1000000000000, 'decimals' => 2),
            'status' => array(ColumnType::Varchar, 'maxLength' => 10),
            'reference' => array(AttributeType::String, 'required' => false),
            'message' => array(AttributeType::String, 'required' => false, 'column' => ColumnType::Text),
            'response' => array(AttributeType::String, 'required' => false, 'column' => ColumnType::Text)
        );
    }

    public function defineRelations()
    {
        return array(
            'parent' => array(static::BELONGS_TO, 'Cellar_TransactionRecord', 'onDelete' => static::CASCADE),
            'user' => array(static::BELONGS_TO, 'UserRecord', 'userId', 'required' => false, 'onDelete' => static::CASCADE),
            'order' => array(static::BELONGS_TO, 'Cellar_OrderRecord', 'orderId', 'required' => true, 'onDelete' => static::CASCADE),
        );
    }

    public function type()
    {
        return $this->type;
    }

    public function status()
    {
        return $this->status;
    }

    public function message()
    {
        return $this->message;
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
