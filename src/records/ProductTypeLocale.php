<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Site;

/**
 * Product type locale record.
 *
 * @property int         $productTypeId
 * @property int         $localeId
 * @property string      $urlFormat
 *
 * @property Site        $locale
 * @property ProductType $productType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ProductTypeLocale extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc BaseRecord::getTableName()
     *
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_producttypes_i18n}}';
    }

//    /**
//     * @inheritDoc BaseRecord::defineRelations()
//     *
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'productType' => [static::BELONGS_TO, 'ProductType', 'required' => true, 'onDelete' => static::CASCADE],
//            'site' => [static::BELONGS_TO, 'Site', 'locale', 'required' => true, 'onDelete' => static::CASCADE, 'onUpdate' => static::CASCADE],
//        ];
//    }
//
//    /**
//     * @inheritDoc BaseRecord::defineIndexes()
//     *
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['productTypeId', 'locale'], 'unique' => true],
//        ];
//    }
//
//    // Protected Methods
//    // =========================================================================
//
//    /**
//     * @inheritDoc BaseRecord::defineAttributes()
//     *
//     * @return array
//     */
//    protected function defineAttributes()
//    {
//        return [
//            'locale' => [AttributeType::Locale, 'required' => true],
//            'urlFormat' => AttributeType::UrlFormat
//        ];
//    }
}
