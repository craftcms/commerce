<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Product type record.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $handle
 * @property bool        $hasUrls
 * @property bool        $hasDimensions
 * @property bool        $hasVariants
 * @property bool        $hasVariantTitleField
 * @property string      $template
 * @property string      $titleFormat
 * @property string      $skuFormat
 * @property string      $descriptionFormat
 * @property int         $fieldLayoutId
 * @property int         $variantFieldLayoutId
 *
 * @property FieldLayout $fieldLayout
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ProductType extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_producttypes}}';
    }

    public function getProductTypesShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ProductTypeShippingCategory::class, ['productTypeId', 'id']);
    }

    public function getShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ShippingCategory::class, ['id', 'shippingCategoryId'])->via('productTypesShippingCategories');
    }

    public function getProductTypesTaxCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ProductTypeTaxCategory::class, ['productTypeId', 'id']);
    }

    public function getTaxCategories(): ActiveQueryInterface
    {


        return $this->hasMany(TaxCategory::class, ['id', 'taxCategoryId'])->via('productTypesTaxCategories');
    }

    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id', 'fieldLayoutId']);
    }

    public function getVariantFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id', 'variantFieldLayoutId']);
    }

}
