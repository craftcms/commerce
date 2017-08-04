<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Discount Product type record.
 *
 * @property int                          $id
 * @property int                          $discountId
 * @property \yii\db\ActiveQueryInterface $productType
 * @property \yii\db\ActiveQueryInterface $discount
 * @property int                          $productTypeId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class DiscountProductType extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_discount_producttypes}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['discountId', 'productTypeId'], 'unique', 'targetAttribute' => ['discountId', 'productTypeId']]
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
    public function getProductType(): ActiveQueryInterface
    {
        return $this->hasOne(ProductType::class, ['id' => 'productTypeId']);
    }
}