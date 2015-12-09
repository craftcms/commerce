<?php
namespace Craft;

/**
 * Sale product record.
 *
 * @property int $id
 * @property int $saleId
 * @property int $productId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_SaleProductRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_sale_products';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['saleId', 'productId'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'sale' => [
                static::BELONGS_TO,
                'Commerce_SaleRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'product' => [
                static::BELONGS_TO,
                'Commerce_ProductRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'saleId' => [AttributeType::Number, 'required' => true],
            'productId' => [AttributeType::Number, 'required' => true],
        ];
    }

}