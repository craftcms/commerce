<?php
namespace Craft;

/**
 * Class Commerce_OrderStatusEmailRecord
 *
 * @property int                      orderStatusId
 * @property int                      emailId
 *
 * @property Commerce_OrderStatusRecord orderStatus
 * @property Commerce_EmailRecord       email
 * @package Craft
 */
class Commerce_OrderStatusEmailRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return "commerce_orderstatus_emails";
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'orderStatus' => [
				static::BELONGS_TO,
				'Commerce_OrderStatusRecord',
				'required' => true,
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE
			],
			'email'       => [
				static::BELONGS_TO,
				'Commerce_EmailRecord',
				'required' => true,
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE
			],
		];
	}

}