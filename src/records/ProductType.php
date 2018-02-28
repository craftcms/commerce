<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Product type record.
 *
 * @property string $descriptionFormat
 * @property FieldLayout $fieldLayout
 * @property int $fieldLayoutId
 * @property string $handle
 * @property bool $hasDimensions
 * @property bool $hasVariants
 * @property bool $hasVariantTitleField
 * @property int $id
 * @property string $name
 * @property ActiveQueryInterface $productTypesShippingCategories
 * @property ActiveQueryInterface $productTypesTaxCategories
 * @property ActiveQueryInterface $shippingCategories
 * @property string $skuFormat
 * @property ActiveQueryInterface $taxCategories
 * @property string $titleFormat
 * @property ActiveQueryInterface $variantFieldLayout
 * @property int $variantFieldLayoutId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductType extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
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
