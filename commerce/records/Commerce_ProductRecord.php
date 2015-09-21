<?php

namespace Craft;

/**
 * Class Commerce_ProductRecord
 *
 * @property int                      id
 * @property int                      taxCategoryId
 * @property int                      typeId
 * @property int                      authorId
 * @property DateTime                 availableOn
 * @property DateTime                 expiresOn
 * @property bool                     promotable
 * @property bool                     freeShipping
 *
 * @property Commerce_VariantRecord     $implicit
 * @property Commerce_VariantRecord[]   variants
 * @property Commerce_TaxCategoryRecord taxCategory
 * @package Craft
 */
class Commerce_ProductRecord extends BaseRecord
{

	/**
	 * @return string
	 */
	public function getTableName ()
	{
		return 'commerce_products';
	}

	/**
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'element'     => [
				static::BELONGS_TO,
				'ElementRecord',
				'id',
				'required' => true,
				'onDelete' => static::CASCADE
			],
			'type'        => [
				static::BELONGS_TO,
				'Commerce_ProductTypeRecord',
				'onDelete' => static::CASCADE
			],
			'author'      => [
				static::BELONGS_TO,
				'UserRecord',
				'onDelete' => static::CASCADE
			],
			'implicit'    => [
				static::HAS_ONE,
				'Commerce_VariantRecord',
				'productId',
				'condition' => 'implicit.isImplicit = 1'
			],
			'variants'    => [
				static::HAS_MANY,
				'Commerce_VariantRecord',
				'productId'
			],
			'taxCategory' => [
				static::BELONGS_TO,
				'Commerce_TaxCategoryRecord',
				'required' => true
			],
		];
	}

	/**
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['typeId']],
			['columns' => ['availableOn']],
			['columns' => ['expiresOn']],
		];
	}

	/**
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'availableOn'  => AttributeType::DateTime,
			'expiresOn'    => AttributeType::DateTime,
			'promotable'   => AttributeType::Bool,
			'freeShipping' => AttributeType::Bool
		];
	}

}