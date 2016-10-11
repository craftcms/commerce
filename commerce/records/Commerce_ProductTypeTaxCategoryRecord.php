<?php
namespace Craft;

/**
 * Product type tax category record.
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
class Commerce_ProductTypeTaxCategoryRecord extends BaseRecord
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
        return 'commerce_producttypes_taxcategories';
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
            'taxCategory' => [static::BELONGS_TO, 'Commerce_TaxCategoryRecord', 'required' => true, 'onDelete' => static::CASCADE],
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
            ['columns' => ['productTypeId', 'taxCategoryId'], 'unique' => true],
        ];
    }
}
