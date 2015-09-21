<?php
namespace Craft;

/**
 * Class Commerce_TransactionRecord
 *
 * @package Craft
 *
 * @property int                        $id
 * @property string                     $hash
 * @property string                     $type
 * @property float                      $amount
 * @property string                     status
 * @property string                     reference
 * @property string                     message
 * @property string                     response
 *
 * @property int                        parentId
 * @property int                        userId
 * @property int                        paymentMethodId
 * @property int                        orderId
 *
 * @property Commerce_TransactionRecord   parent
 * @property Commerce_PaymentMethodRecord paymentMethod
 * @property Commerce_OrderRecord         order
 * @property UserRecord                 user
 */
class Commerce_TransactionRecord extends BaseRecord
{
	const AUTHORIZE = 'authorize';
	const CAPTURE = 'capture';
	const PURCHASE = 'purchase';
	const REFUND = 'refund';

	const PENDING = 'pending';
	const REDIRECT = 'redirect';
	const SUCCESS = 'success';
	const FAILED = 'failed';
	/* @var int $total */
	public $total = 0;
	/* @var array $types */
	private $types = [
		self::AUTHORIZE,
		self::CAPTURE,
		self::PURCHASE,
		self::REFUND
	];

	/**
	 * @var array
	 */
	private $statuses = [
		self::PENDING,
		self::REDIRECT,
		self::SUCCESS,
		self::FAILED
	];

	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_transactions';
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'parent'        => [
				self::BELONGS_TO,
				'Commerce_TransactionRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE
			],
			'paymentMethod' => [
				self::BELONGS_TO,
				'Commerce_PaymentMethodRecord',
				'onDelete' => self::RESTRICT,
				'onUpdate' => self::CASCADE
			],
			'order'         => [
				self::BELONGS_TO,
				'Commerce_OrderRecord',
				'required' => true,
				'onDelete' => self::CASCADE
			],
			'user'          => [
				self::BELONGS_TO,
				'UserRecord',
				'onDelete' => self::RESTRICT
			],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'hash'      => [AttributeType::String, 'maxLength' => 32],
			'type'      => [
				AttributeType::Enum,
				'values'   => $this->types,
				'required' => true
			],
			'amount'    => [
				AttributeType::Number,
				'min'      => -1000000000000,
				'max'      => 1000000000000,
				'decimals' => 2
			],
			'status'    => [
				AttributeType::Enum,
				'values'   => $this->statuses,
				'required' => true
			],
			'reference' => [AttributeType::String],
			'message'   => [AttributeType::Mixed],
			'response'  => [AttributeType::Mixed],
			'orderId'   => [AttributeType::Number, 'required' => true],
		];
	}
}
