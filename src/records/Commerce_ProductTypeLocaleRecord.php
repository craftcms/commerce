<?php
namespace Craft;

/**
 * Product type locale record.
 *
 * @property int $productTypeId
 * @property int $localeId
 * @property string $urlFormat
 *
 * @property LocaleRecord $locale
 * @property Commerce_ProductTypeRecord $productType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_ProductTypeLocaleRecord extends BaseRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc BaseRecord::getTableName()
     *
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_producttypes_i18n';
    }

    /**
     * @inheritDoc BaseRecord::defineRelations()
     *
     * @return array
     */
    public function defineRelations()
    {
        return [
            'productType' => [static::BELONGS_TO, 'Commerce_ProductTypeRecord', 'required' => true, 'onDelete' => static::CASCADE],
            'locale' => [static::BELONGS_TO, 'LocaleRecord', 'locale', 'required' => true, 'onDelete' => static::CASCADE, 'onUpdate' => static::CASCADE],
        ];
    }

    /**
     * @inheritDoc BaseRecord::defineIndexes()
     *
     * @return array
     */
    public function defineIndexes()
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
    protected function defineAttributes()
    {
        return [
            'locale' => [AttributeType::Locale, 'required' => true],
            'urlFormat' => AttributeType::UrlFormat
        ];
    }
}
