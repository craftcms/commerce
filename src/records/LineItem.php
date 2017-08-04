<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Line Item record.
 *
 * @property int                          $id
 * @property float                        $price
 * @property float                        $saleAmount
 * @property float                        $salePrice
 * @property float                        $tax
 * @property float                        $taxIncluded
 * @property float                        $shippingCost
 * @property float                        $discount
 * @property float                        $weight
 * @property float                        $height
 * @property float                        $width
 * @property float                        $length
 * @property float                        $total
 * @property int                          $qty
 * @property string                       $note
 * @property string                       $snapshot
 *
 * @property int                          $orderId
 * @property int                          $purchasableId
 * @property mixed                        $options
 * @property string                       $optionsSignature
 * @property int                          $taxCategoryId
 * @property int                          $shippingCategoryId
 *
 * @property Order                        $order
 * @property Variant                      $variant
 * @property \yii\db\ActiveQueryInterface $purchasable
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
class LineItem extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_lineitems}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['orderId', 'purchasableId', 'optionsSignature'], 'unique', 'targetAttribute' => ['orderId', 'purchasableId', 'optionsSignature']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'purchasableId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxCategory(): ActiveQueryInterface
    {
        return $this->hasOne(TaxCategory::class, ['id' => 'taxCategoryId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getShippingCategory(): ActiveQueryInterface
    {
        return $this->hasOne(ShippingCategory::class, ['id' => 'shippingCategoryId']);
    }
}
