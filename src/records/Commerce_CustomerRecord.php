<?php
namespace Craft;

/**
 * Customer record.
 *
 * @property int                      $id
 * @property string                   $email
 * @property int                      $userId
 * @property int                      $lastUsedBillingAddressId
 * @property int                      $lastUsedShippingAddressId
 *
 * @property Commerce_AddressRecord[] $addresses
 * @property Commerce_OrderRecord[]   $orders
 * @property UserRecord               $user
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_CustomerRecord extends BaseRecord
{
	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_customers';
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'user'      => [static::BELONGS_TO, 'UserRecord'],
			'addresses' => [
				static::HAS_MANY,
				'Commerce_AddressRecord',
				'customerId'
			],
			'orders'    => [
				static::HAS_MANY,
				'Commerce_OrderRecord',
				'customerId'
			],
		];
	}

	/**
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'email'                     => AttributeType::Email,
			'lastUsedBillingAddressId'  => AttributeType::Number,
			'lastUsedShippingAddressId' => AttributeType::Number
		];
	}
}