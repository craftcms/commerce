<?php

namespace Craft;

/**
 * Class Market_ProductRecord
 *
 * @property int taxCategoryId
 * @property int typeId
 * @property int authorId
 * @property DateTime availableOn
 * @property DateTime expiresOn

 * @property Market_VariantRecord   $master
 * @property Market_VariantRecord[] $variants
 * @property Market_VariantRecord[] variantsWithMaster
 * @property Market_TaxCategoryRecord taxCategory
 * @package Craft
 */
class Market_ProductRecord extends BaseRecord
{

	/**
	 * @inheritDoc BaseRecord::getTableName()
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return 'market_products';
	}

	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'element'            => array(static::BELONGS_TO, 'ElementRecord', 'id', 'required' => true, 'onDelete' => static::CASCADE),
			'type'               => array(static::BELONGS_TO, 'Market_ProductTypeRecord', 'onDelete' => static::CASCADE),
			'author'             => array(static::BELONGS_TO, 'UserRecord', 'onDelete' => static::CASCADE),
			'optionTypes'        => array(static::MANY_MANY, 'Market_OptionTypeRecord', 'market_product_optiontypes(productId, optionTypeId)'),
			'master'             => array(static::HAS_ONE, 'Market_VariantRecord', 'productId', 'condition' => 'master.isMaster = 1'),
			'variants'           => array(static::HAS_MANY, 'Market_VariantRecord', 'productId', 'condition' => 'master.isMaster = 0'),
			'variantsWithMaster' => array(static::HAS_MANY, 'Market_VariantRecord', 'productId', 'onDelete' => static::CASCADE),
			'taxCategory'        => array(static::BELONGS_TO, 'Market_TaxCategoryRecord', 'required' => true),
		);
	}

	/**
	 * @inheritDoc BaseRecord::defineIndexes()
	 *
	 * @return array
	 */
	public function defineIndexes()
	{
		return array(
			array('columns' => array('typeId')),
			array('columns' => array('availableOn')),
			array('columns' => array('expiresOn')),
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
			'availableOn' => AttributeType::DateTime,
			'expiresOn'   => AttributeType::DateTime,
		);
	}

}