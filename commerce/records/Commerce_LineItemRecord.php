<?php
namespace Craft;

/**
 * Line Item record.
 *
 * @property int $id
 * @property float $price
 * @property float $saleAmount
 * @property float $salePrice
 * @property float $tax
 * @property float $taxIncluded
 * @property float $shippingCost
 * @property float $discount
 * @property float $weight
 * @property float $height
 * @property float $width
 * @property float $length
 * @property float $total
 * @property int $qty
 * @property string $note
 * @property string $snapshot
 *
 * @property int $orderId
 * @property int $purchasableId
 * @property mixed $options
 * @property string $optionsSignature
 * @property int $taxCategoryId
 * @property int $shippingCategoryId
 *
 * @property Commerce_OrderRecord $order
 * @property Commerce_VariantRecord $variant
 * @property Commerce_TaxCategoryRecord $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_LineItemRecord extends BaseRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return "commerce_lineitems";
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['orderId', 'purchasableId', 'optionsSignature'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'order' => [
                static::BELONGS_TO,
                'Commerce_OrderRecord',
                'required' => true,
                'onDelete' => static::CASCADE
            ],
            'purchasable' => [
                static::BELONGS_TO,
                'ElementRecord',
                'onUpdate' => self::CASCADE,
                'onDelete' => self::SET_NULL
            ],
            'taxCategory' => [
                static::BELONGS_TO,
                'Commerce_TaxCategoryRecord',
                'onUpdate' => self::CASCADE,
                'onDelete' => self::RESTRICT,
                'required' => true
            ],
            'shippingCategory' => [
                static::BELONGS_TO,
                'Commerce_ShippingCategoryRecord',
                'onUpdate' => self::CASCADE,
                'onDelete' => self::RESTRICT,
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
            'options' => AttributeType::Mixed,
            'optionsSignature' => [AttributeType::String, 'required' => true],
            'price' => [
                AttributeType::Number,
                'min' => 0,
                'decimals' => 4,
                'required' => true
            ],
            'saleAmount' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'salePrice' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'tax' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'taxIncluded' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'shippingCost' => [
                AttributeType::Number,
                'min' => 0,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'discount' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'weight' => [
                AttributeType::Number,
                'min' => 0,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'height' => [
                AttributeType::Number,
                'min' => 0,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'length' => [
                AttributeType::Number,
                'min' => 0,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'width' => [
                AttributeType::Number,
                'min' => 0,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'total' => [
                AttributeType::Number,
                'min' => 0,
                'decimals' => 4,
                'required' => true,
                'default' => 0
            ],
            'qty' => [
                AttributeType::Number,
                'min' => 0,
                'required' => true
            ],
            'note' => AttributeType::Mixed,
            'snapshot' => [AttributeType::Mixed, 'required' => true],
            'taxCategoryId' => [AttributeType::Number, 'required' => true],
            'shippingCategoryId' => [AttributeType::Number, 'required' => true],
        ];
    }
}
