<?php

namespace Craft;

use Market\Interfaces\Purchasable;
use Market\Traits\Market_ModelRelationsTrait;

/**
 * Class Market_LineItemModel
 *
 * @package Craft
 *
 * @property int                     id
 * @property float                   price
 * @property float                   saleAmount
 * @property float                   $tax
 * @property float                   shippingCost
 * @property float                   discount
 * @property float                   weight
 * @property float                   height
 * @property float                   width
 * @property float                   length
 * @property float                   total
 * @property int                     qty
 * @property string                  note
 * @property string                  snapshot
 *
 * @property int                     orderId
 * @property int                     purchasableId
 * @property int                     taxCategoryId
 *
 * @property bool                    underSale
 *
 * @property Purchasable             $purchasable
 * @property Market_OrderModel       order
 * @property Market_TaxCategoryModel taxCategory
 */
class Market_LineItemModel extends BaseModel
{
    use Market_ModelRelationsTrait;

    /**
     * @return bool
     */
    public function getUnderSale()
    {
        return $this->saleAmount != 0;
    }

    public function getSubtotalWithSale()
    {
        return $this->qty * ($this->price + $this->saleAmount);
    }

    public function getPriceWithoutShipping()
    {
        return $this->price + $this->discount + $this->saleAmount;
    }

    public function getPurchasable()
    {
        if (!$this->purchasableId) {
            return null;
        }

        return craft()->elements->getElementById($this->purchasableId);
    }

    /**
     * @return bool False when no related variant exists
     */
    public function refreshFromPurchasable()
    {
        if (!$this->purchasable || !$this->purchasable->id) {
            return false;
        }

        $this->fillFromPurchasable($this->purchasable);

        return true;
    }

    /**
     * @param Purchasable $purchasable
     */
    public function fillFromPurchasable(Purchasable $purchasable)
    {
        $this->price         = $purchasable->price;
        $this->weight        = $purchasable->weight * 1; //converting nulls
        $this->height        = $purchasable->height * 1; //converting nulls
        $this->length        = $purchasable->length * 1; //converting nulls
        $this->width         = $purchasable->width * 1; //converting nulls
        $this->snapshot      = $purchasable->attributes;

        if ($purchasable instanceof Market_VariantModel || $purchasable instanceof Market_ProductModel) {

            $this->taxCategoryId = $purchasable->product->taxCategoryId;

            $sales = craft()->market_sale->getForVariant($purchasable);

            foreach ($sales as $sale) {
                $this->saleAmount += $sale->calculateTakeoff($this->price);
            }

            if ($this->saleAmount > $this->price) {
                $this->saleAmount = $this->price;
            }
        }else{
            $this->saleAmount = $this->price;
        }

    }

    protected function defineAttributes()
    {
        return [
            'id'             => AttributeType::Number,
            'price'          => [
                AttributeType::Number,
                'min'      => 0,
                'decimals' => 4,
                'required' => true
            ],
            'saleAmount'     => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'tax'      => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'shippingCost' => [
                AttributeType::Number,
                'min'      => 0,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'discount' => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'weight'         => [
                AttributeType::Number,
                'min'      => 0,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'length'         => [
                AttributeType::Number,
                'min'      => 0,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'height'         => [
                AttributeType::Number,
                'min'      => 0,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'width'          => [
                AttributeType::Number,
                'min'      => 0,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'total'          => [
                AttributeType::Number,
                'min'      => 0,
                'decimals' => 4,
                'required' => true,
                'default'  => 0
            ],
            'qty'            => [
                AttributeType::Number,
                'min'      => 0,
                'required' => true
            ],
            'snapshot'    => [AttributeType::Mixed, 'required' => true],
            'note'    => AttributeType::Mixed,
            'purchasableId'  => AttributeType::Number,
            'orderId'        => AttributeType::Number,
            'taxCategoryId'  => [AttributeType::Number, 'required' => true],
        ];
    }

    public function toArray()
    {
        $data = [];
        foreach($this->defineAttributes() as $key => $val){
            $data[$key] = $this->getAttribute($key, true);
        }
        return $data;
    }
}