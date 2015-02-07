<?php

namespace Craft;

/**
 * Class Market_TransactionModel
 *
 * @package Craft
 *
 * @property int                         $id
 * @property string                      $hash
 * @property string                      $type
 * @property float                       $amount
 * @property string                      status
 * @property string                      reference
 * @property string                      message
 * @property string                      response
 *
 * @property int                         parentId
 * @property int                         userId
 * @property int                         paymentMethodId
 * @property int                         orderId
 *
 * @property Market_TransactionRecord   $parent
 * @property Market_PaymentMethodRecord paymentMethod
 * @property UserRecord                  $user
 */
class Market_TransactionModel extends BaseModel
{
	const AUTHORIZE = 'authorize';
	const CAPTURE = 'capture';
	const PURCHASE = 'purchase';
	const REFUND = 'refund';

	const PENDING = 'pending';
	const REDIRECT = 'redirect';
	const SUCCESS = 'success';
	const FAILED = 'failed';

	public function __construct($attributes = NULL)
	{
		// generate unique hash
		$this->hash = md5(uniqid(mt_rand(), true));

		parent::__construct($attributes);
	}

	/**
	 * @return UserModel|null
	 */
	public function getUser()
	{
		return $this->userId ? craft()->users->getUserById($this->userId) : NULL;
	}

	/**
	 * @return Market_PaymentMethodModel|null
	 */
	public function getPaymentMethod()
	{
		return $this->paymentMethodId ? craft()->market_paymentMethod->getById($this->paymentMethodId) : NULL;
	}

	protected function defineAttributes()
	{
		return array(
			'id'              => AttributeType::Number,
			'userId'          => AttributeType::Number,
			'orderId'         => AttributeType::Number,
			'hash'            => AttributeType::String,
			'paymentMethodId' => AttributeType::Number,
			'type'            => AttributeType::String,
			'amount'          => AttributeType::Number,
			'status'          => AttributeType::String,
			'reference'       => AttributeType::String,
			'message'         => AttributeType::String,
			'response'        => AttributeType::String,
		);
	}

	/**
	 * @return null
	 */
//    public function getOrder()
//    {
//        return $this->orderId ? craft()->cellar_orders->getOrder($this->orderId) : null;
//    }
}
