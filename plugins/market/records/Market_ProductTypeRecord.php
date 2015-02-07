<?php
namespace Craft;

/**
 * Class Market_ProductTypeRecord
 *
 * @property int               id
 * @property string            name
 * @property string            handle
 * @property int               fieldLayoutId
 *
 * @property FieldLayoutRecord fieldLayout
 * @package Craft
 */
class Market_ProductTypeRecord extends BaseRecord
{

	/**
	 * @inheritDoc BaseRecord::getTableName()
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_producttypes';
	}

	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'fieldLayout' => array(static::BELONGS_TO, 'FieldLayoutRecord', 'onDelete' => static::SET_NULL),
		);
	}


	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes()
	{
		return array(
			'name'          => array(AttributeType::Name, 'required' => true),
			'handle'        => array(AttributeType::Handle, 'required' => true),
			'fieldLayoutId' => AttributeType::Number,
		);
	}

}