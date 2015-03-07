<?php

namespace Craft;

/**
 * Class Market_ProductRecord
 *
 * @property int                       taxCategoryId
 * @property int                       typeId
 * @property int                       authorId
 * @property DateTime                  availableOn
 * @property DateTime                  expiresOn
 * @property Market_VariantRecord      $master
 * @property Market_VariantRecord[]    allVariants
 * @property Market_TaxCategoryRecord  taxCategory
 * @property Market_OptionTypeRecord[] optionTypes
 * @package Craft
 */
class Market_ProductRecord extends BaseRecord
{

	/**
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_products';
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return [
			'element'     => [static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE],
			'type'        => [static::BELONGS_TO, 'Market_ProductTypeRecord', 'onDelete' => static::CASCADE],
			'author'      => [static::BELONGS_TO, 'UserRecord', 'onDelete' => static::CASCADE],
			'optionTypes' => [static::MANY_MANY, 'Market_OptionTypeRecord', 'market_product_optiontypes(productId, optionTypeId)'],
			'master'      => [static::HAS_ONE, 'Market_VariantRecord', 'productId', 'condition' => 'master.isMaster = 1'],
			'allVariants' => [static::HAS_MANY, 'Market_VariantRecord', 'productId'],
			'taxCategory' => [static::BELONGS_TO, 'Market_TaxCategoryRecord', 'required' => true],
		];
	}

	/**
	 * @return array
	 */
	public function defineIndexes()
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
	protected function defineAttributes()
	{
		return [
			'availableOn' => AttributeType::DateTime,
			'expiresOn'   => AttributeType::DateTime,
		];
	}

}