<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Variant record.
 *
 * @property int                          $id
 * @property int                          $productId
 * @property string                       $sku
 * @property bool                         $isDefault
 * @property float                        $price
 * @property int                          $sortOrder
 * @property float                        $width
 * @property float                        $height
 * @property float                        $length
 * @property float                        $weight
 * @property int                          $stock
 * @property bool                         $unlimitedStock
 * @property int                          $minQty
 * @property int                          $maxQty
 *
 * @property \yii\db\ActiveQueryInterface $element
 * @property Product                      $product
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Variant extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_variants}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['sku'], 'unique']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['id', 'productId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id', 'id']);
    }
}
