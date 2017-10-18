<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Discount product record.
 *
 * @property int                          $id
 * @property int                          $discountId
 * @property \yii\db\ActiveQueryInterface $discount
 * @property \yii\db\ActiveQueryInterface $product
 * @property int                          $productId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class DiscountProduct extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%commerce_discount_products}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['discountId', 'productId'], 'unique', 'targetAttribute' => ['discountId', 'productId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscount(): ActiveQueryInterface
    {
        return $this->hasOne(Discount::class, ['id' => 'discountId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['id' => 'productId']);
    }
}
