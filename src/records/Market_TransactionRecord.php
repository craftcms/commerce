<?php
namespace Craft;

use Omnipay\Common\Exception\OmnipayException;

/**
 * Class Market_TransactionRecord
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
 * @property Market_TransactionRecord   parent
 * @property Market_PaymentMethodRecord paymentMethod
 * @property Market_OrderRecord         order
 */
class Market_TransactionRecord extends BaseRecord
{
    const AUTHORIZE = 'authorize';
    const CAPTURE = 'capture';
    const PURCHASE = 'purchase';
    const REFUND = 'refund';

    const PENDING = 'pending';
    const REDIRECT = 'redirect';
    const SUCCESS = 'success';
    const FAILED = 'failed';

    private $types = [self::AUTHORIZE, self::CAPTURE, self::PURCHASE, self::REFUND];
    private $statuses = [self::PENDING, self::REDIRECT, self::SUCCESS, self::FAILED];

	public function getTableName()
	{
		return 'market_transactions';
	}

	protected function defineAttributes()
	{
        return [
            'hash'      => [AttributeType::String, 'maxLength' => 32],
            'type'      => [AttributeType::Enum, 'values' => $this->types, 'required' => true],
            'amount'    => [AttributeType::Number, 'min' => -1000000000000, 'max' => 1000000000000, 'decimals' => 2],
            'status'    => [AttributeType::Enum, 'values' => $this->statuses, 'required' => true],
            'reference' => [AttributeType::String],
            'message'   => [AttributeType::Mixed],
            'response'  => [AttributeType::Mixed],

            'orderId'   => [AttributeType::Number, 'required' => true],
        ];
    }

    public function defineRelations()
    {
        return [
            'parent'        => [self::BELONGS_TO, 'Market_TransactionRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE],
            'paymentMethod' => [self::BELONGS_TO, 'Market_PaymentMethodRecord', 'onDelete' => self::RESTRICT, 'onUpdate' => self::CASCADE],
            'order'         => [self::BELONGS_TO, 'Market_OrderRecord', 'required' => true, 'onDelete' => self::CASCADE],
        ];
    }
}
