<?php

namespace Craft;

/**
 * Class Market_CustomerAddressRecord
 *
 * @property int                   $id
 * @property int                   addressId
 * @property int                   customerId

 * @property Market_AddressRecord  $address
 * @property Market_CustomerRecord $customer
 * @package Craft
 */
class Market_CustomerAddressRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'market_customeraddresses';
	}

	/**
	 * @inheritDoc BaseRecord::defineIndexes()
	 *
	 * @return array
	 */
	public function defineIndexes()
	{
		return [
			['columns' => ['customerId', 'addressId'], 'unique' => true],
		];
	}

	public function defineRelations()
	{
		return [
			'address' => [static::BELONGS_TO, 'Market_AddressRecord', 'required' => true],
			'customer' => [static::BELONGS_TO, 'Market_CustomerRecord', 'required' => true],
		];
	}

	protected function defineAttributes()
	{
		return [
			'addressId' => [AttributeType::Number, 'required' => true],
			'customerId' => [AttributeType::Number, 'required' => true],
		];
	}


}