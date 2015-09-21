<?php

namespace Craft;

/**
 * Class Market_SaleUserGroupRecord
 *
 * @property int id
 * @property int saleId
 * @property int userGroupId
 * @package Craft
 */
class Market_SaleUserGroupRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'market_sale_usergroups';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['saleId', 'userGroupId'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'sale'      => [
				static::BELONGS_TO,
				'Market_SaleRecord',
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
			'saleId'      => [AttributeType::Number, 'required' => true],
			'userGroupId' => [AttributeType::Number, 'required' => true],
		];
	}

}