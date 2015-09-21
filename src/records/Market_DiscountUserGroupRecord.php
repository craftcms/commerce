<?php

namespace Craft;

/**
 * Class Market_DiscountUserGroupRecord
 *
 * @property int id
 * @property int discountId
 * @property int userGroupId
 * @package Craft
 */
class Market_DiscountUserGroupRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'market_discount_usergroups';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['discountId', 'userGroupId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'discount'  => [
				static::BELONGS_TO,
				'Market_DiscountRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
			'userGroup' => [
				static::BELONGS_TO,
				'UserGroupRecord',
				'onDelete' => self::CASCADE,
				'onUpdate' => self::CASCADE,
				'required' => true
			],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'discountId'  => [AttributeType::Number, 'required' => true],
			'userGroupId' => [AttributeType::Number, 'required' => true],
		];
	}

}