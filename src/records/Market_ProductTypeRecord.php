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
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_producttypes';
	}

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['handle'], 'unique' => true],
        ];
    }

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'fieldLayout' => [static::BELONGS_TO, 'FieldLayoutRecord', 'onDelete' => static::SET_NULL],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes()
	{
		return [
			'name'   => [AttributeType::Name, 'required' => true],
			'handle' => [AttributeType::Handle, 'required' => true],
		];
	}

}