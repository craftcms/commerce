<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Sale product record.
 *
 * @property int                          $id
 * @property int                          $saleId
 * @property \yii\db\ActiveQueryInterface $sale
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
class SaleProduct extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_sale_products}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['saleId', 'productId'], 'unique', 'targetAttribute' => ['saleId', 'productId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getSale(): ActiveQueryInterface
    {
        return $this->hasOne(Sale::class, ['saleId' => 'id']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getProduct(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['saleId' => 'id']);
    }
}