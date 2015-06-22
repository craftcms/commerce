<?php

namespace Craft;

/**
 * Class Market_PurchasableRecord
 *
 * @property int                  id
 * @property int                  productId
 * @property bool                 isMaster
 * @property string               sku
 * @property float                price
 * @property float                width
 * @property float                height
 * @property float                length
 * @property float                weight
 * @property int                  stock
 * @property bool                 unlimitedStock
 * @property int                  minQty
 * @property DateTime             deletedAt
 *
 * @property Market_ProductRecord $product
 * @package Craft
 */
class Market_PurchasableRecord extends BaseRecord
{

    public function getTableName()
    {
        return 'market_purchasable';
    }

    public function defineIndexes()
    {
        return [
            ['columns' => ['sku'], 'unique' => true],
        ];
    }

    protected function defineAttributes()
    {
        return [
            'sku'   => [AttributeType::String, 'required' => true],
            'price' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true
            ]
        ];
    }

}