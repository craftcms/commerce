<?php
namespace Craft;

/**
 * Class Market_CustomerRecord
 * @package Craft
 *
 * @property string email
 * @property int userId
 * @property int elementId
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
			'email' => [AttributeType::String, 'required' => true],
		);
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'user'      => [static::BELONGS_TO, 'UserRecord'],
			'element'   => [static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE],
			'addresses' => [static::HAS_MANY, 'Market_CustomerAddressRecord', 'customerId'],
		];
	}
}