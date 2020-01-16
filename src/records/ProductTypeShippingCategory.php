<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Product type shipping category record.
 *
 * @property ProductType $productType
 * @property int $productTypeId
 * @property ShippingCategory $shippingCategory
 * @property int $shippingCategoryId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductTypeShippingCategory extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PRODUCTTYPES_SHIPPINGCATEGORIES;
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
    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id', 'shippingCategoryId']);
    }
}
