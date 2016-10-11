<?php
namespace Craft;

/**
 * Product type shipping category record.
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
 * @since     1.2
 */
class Commerce_ProductTypeShippingCategoryRecord extends BaseRecord
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
        return 'commerce_producttypes_shippingcategories';
    }

    /**
     * @inheritDoc BaseRecord::defineRelations()
     *
     * @return array
     */
    public function defineRelations()
    {
        return [
            'productType' => [
                static::BELONGS_TO,
                'Commerce_ProductTypeRecord',
                'required' => true,
                'onDelete' => static::CASCADE,
                'onUpdate' => self::CASCADE,
            ],
            'shippingCategory' => [
                static::BELONGS_TO,
                'Commerce_ShippingCategoryRecord',
                'required' => true,
                'onDelete' => static::CASCADE,
                'onUpdate' => self::CASCADE,
            ]
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
            ['columns' => ['productTypeId', 'shippingCategoryId'], 'unique' => true],
        ];
    }
}
