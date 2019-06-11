<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Product record.
 *
 * @property float defaultHeight
 * @property float defaultLength
 * @property float defaultPrice
 * @property string defaultSku
 * @property int defaultVariantId
 * @property float defaultWeight
 * @property float defaultWidth
 * @property ActiveQueryInterface $element
 * @property DateTime $expiryDate
 * @property bool $freeShipping
 * @property int $id
 * @property DateTime $postDate
 * @property bool $promotable
 * @property bool $availableForPurchase
 * @property ActiveQueryInterface $shippingCategory
 * @property int $shippingCategoryId
 * @property int $taxCategoryId
 * @property TaxCategory $taxCategory
 * @property ActiveQueryInterface $type
 * @property int $typeId
 * @property Variant[] $variants
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Product extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_products}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getVariants(): ActiveQueryInterface
    {
        return $this->hasMany(Variant::class, ['productId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id' => 'productTypeId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id' => 'shippingCategoryId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }
}
