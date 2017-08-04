<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Product record.
 *
 * @property int                          $id
 * @property int                          $taxCategoryId
 * @property int                          $shippingCategoryId
 * @property int                          $typeId
 * @property \DateTime                    $postDate
 * @property \DateTime                    $expiryDate
 * @property bool                         $promotable
 * @property bool                         $freeShipping
 *
 * @property int                          defaultVariantId
 * @property string                       defaultSku
 * @property float                        defaultPrice
 * @property float                        defaultHeight
 * @property float                        defaultLength
 * @property float                        defaultWidth
 * @property float                        defaultWeight
 *
 * @property Variant                      $implicit
 * @property Variant[]                    $variants
 * @property \yii\db\ActiveQueryInterface $element
 * @property \yii\db\ActiveQueryInterface $type
 * @property \yii\db\ActiveQueryInterface $shippingCategory
 * @property TaxCategory                  $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Product extends ActiveRecord
{
    /**
     * @return string
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
