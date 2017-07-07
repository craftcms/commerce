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
 *
 * @property TaxCategory $taxCategory
 * @property ProductType $productType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class ProductTypeTaxCategory extends ActiveRecord
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
        return '{{%commerce_producttypes_taxcategories}}';
    }

    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id', 'productTypeId']);
    }

    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id', 'taxCategoryId']);
    }
}
