<?php
namespace Craft;

/**
 * Class ProductTypeLocaleRecord
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com
 * @package   craft.app.records
 * @since     2.0
 */
class Market_ProductTypeLocaleRecord extends BaseRecord
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseRecord::getTableName()
	 *
	 * @return string
	 */
	public function getTableName ()
	{
		return 'market_producttypes_i18n';
	}

	/**
	 * @inheritDoc BaseRecord::defineRelations()
	 *
	 * @return array
	 */
	public function defineRelations ()
	{
		return [
			'productType' => [static::BELONGS_TO, 'Market_ProductTypeRecord', 'required' => true, 'onDelete' => static::CASCADE],
			'locale'      => [static::BELONGS_TO, 'LocaleRecord', 'locale', 'required' => true, 'onDelete' => static::CASCADE, 'onUpdate' => static::CASCADE],
		];
	}

	/**
	 * @inheritDoc BaseRecord::defineIndexes()
	 *
	 * @return array
	 */
	public function defineIndexes ()
	{
		return [
			['columns' => ['productTypeId', 'locale'], 'unique' => true],
		];
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseRecord::defineAttributes()
	 *
	 * @return array
	 */
	protected function defineAttributes ()
	{
		return [
			'locale'    => [AttributeType::Locale, 'required' => true],
			'urlFormat' => AttributeType::UrlFormat
		];
	}
}
