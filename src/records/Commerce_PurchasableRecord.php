<?php

namespace Craft;

/**
 * Class Commerce_PurchasableRecord
 *
 * @property int                  id
 * @property string               sku
 * @property float                price
 *
 * @property Commerce_VariantRecord $implicit
 * @package Craft
 */
class Commerce_PurchasableRecord extends BaseRecord
{

	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_purchasables';
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['sku'], 'unique' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'element' => [
				static::BELONGS_TO,
				'ElementRecord',
				'id',
				'required' => true,
				'onDelete' => static::CASCADE
			]
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'sku'   => [AttributeType::String, 'required' => true],
			'price' => [
				AttributeType::Number,
				'decimals' => 4,
				'required' => true
			]
		];
	}

}