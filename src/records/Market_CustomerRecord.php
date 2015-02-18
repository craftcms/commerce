<?php
namespace Craft;

/**
 * Class Market_CustomerRecord
 * @package Craft
 *
 * @property int id
 * @property string email
 * @property int userId
 *
 * @property Market_CustomerAddressRecord[] addresses
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
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array(
			'email' => [AttributeType::Email],
		);
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'user'      => [static::BELONGS_TO, 'UserRecord'],
			'addresses' => [static::HAS_MANY, 'Market_CustomerAddressRecord', 'customerId'],
		];
	}
}