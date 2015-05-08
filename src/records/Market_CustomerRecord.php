<?php
namespace Craft;

/**
 * Class Market_CustomerRecord
 *
 * @package Craft
 *
 * @property int                            id
 * @property string                         email
 * @property int                            userId                      userId
 *
 * @property Market_AddressRecord[] addresses
 * @property Market_OrderRecord[]   orders
 * @property UserRecord             user
 */
class Market_CustomerRecord extends BaseRecord
{
	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_customers';
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'user'      => [static::BELONGS_TO, 'UserRecord'],
			'addresses' => [static::HAS_MANY, 'Market_AddressRecord', 'customerId'],
			'orders'    => [static::HAS_MANY, 'Market_OrderRecord', 'customerId'],
		];
	}

	/**
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'email' => [AttributeType::Email],
		];
	}
}