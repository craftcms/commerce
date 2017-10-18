<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Product type record.
 *
 * @property int                          $id
 * @property string                       $name
 * @property string                       $handle
 * @property bool                         $hasUrls
 * @property bool                         $hasDimensions
 * @property bool                         $hasVariants
 * @property bool                         $hasVariantTitleField
 * @property string                       $template
 * @property string                       $titleFormat
 * @property string                       $skuFormat
 * @property string                       $descriptionFormat
 * @property int                          $fieldLayoutId
 * @property int                          $variantFieldLayoutId
 *
 * @property \yii\db\ActiveQueryInterface $shippingCategories
 * @property \yii\db\ActiveQueryInterface $taxCategories
 * @property \yii\db\ActiveQueryInterface $productTypesTaxCategories
 * @property \yii\db\ActiveQueryInterface $variantFieldLayout
 * @property \yii\db\ActiveQueryInterface $productTypesShippingCategories
 *
 * @property FieldLayout                  $fieldLayout
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
    public static function tableName(): string
    {
        return '{{%commerce_producttypes}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProductTypesShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ProductTypeShippingCategory::class, ['productTypeId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ShippingCategory::class, ['id' => 'shippingCategoryId'])
            ->via('productTypesShippingCategories');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProductTypesTaxCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ProductTypeTaxCategory::class, ['productTypeId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxCategories(): ActiveQueryInterface
    {
        return $this->hasMany(TaxCategory::class, ['id' => 'taxCategoryId'])
            ->via('productTypesTaxCategories');
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVariantFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'variantFieldLayoutId']);
    }
}
