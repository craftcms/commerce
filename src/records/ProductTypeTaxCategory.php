<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Product type tax category record.
 *
 * @property int         $productTypeId
 * @property int         $localeId
 * @property string      $uriFormat
 * @property TaxCategory $taxCategory
 * @property ProductType $productType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class ProductTypeTaxCategory extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_producttypes_taxcategories}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id', 'productTypeId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id', 'taxCategoryId']);
    }
}
