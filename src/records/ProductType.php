<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Product type record.
 *
 * @property string $descriptionFormat
 * @property FieldLayout $fieldLayout
 * @property int|null $fieldLayoutId
 * @property string $handle
 * @property bool $enableVersioning
 * @property bool $hasDimensions
 * @property int $id
 * @property int $maxVariants
 * @property string $name
 * @property ActiveQueryInterface $productTypesShippingCategories
 * @property ActiveQueryInterface $productTypesTaxCategories
 * @property ActiveQueryInterface $shippingCategories
 * @property string $skuFormat
 * @property ActiveQueryInterface $taxCategories
 * @property bool $hasVariantTitleField
 * @property string $variantTitleFormat
 * @property string $variantTitleTranslationMethod
 * @property string $variantTitleTranslationKeyFormat
 * @property bool $hasProductTitleField
 * @property string $productTitleFormat
 * @property string $productTitleTranslationMethod
 * @property string $productTitleTranslationKeyFormat
 * @property ActiveQueryInterface $variantFieldLayout
 * @property int|null $variantFieldLayoutId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PRODUCTTYPES;
    }

    public function getProductTypesShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ProductTypeShippingCategory::class, ['productTypeId' => 'id']);
    }

    public function getShippingCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ShippingCategory::class, ['id' => 'shippingCategoryId'])
            ->via('productTypesShippingCategories');
    }

    public function getProductTypesTaxCategories(): ActiveQueryInterface
    {
        return $this->hasMany(ProductTypeTaxCategory::class, ['productTypeId' => 'id']);
    }

    public function getTaxCategories(): ActiveQueryInterface
    {
        return $this->hasMany(TaxCategory::class, ['id' => 'taxCategoryId'])
            ->via('productTypesTaxCategories');
    }

    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }

    /**
     * @noinspection PhpUnused
     */
    public function getVariantFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'variantFieldLayoutId']);
    }
}
