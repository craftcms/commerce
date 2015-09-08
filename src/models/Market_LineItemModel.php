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
 * @property float                   salePrice
 * @property float                   tax
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
 * @property bool                    onSale
 *
 * @property Purchasable             $purchasable
 * @property Market_OrderModel       order
 * @property Market_TaxCategoryModel taxCategory
 */
class Market_LineItemModel extends BaseModel
{
    use Market_ModelRelationsTrait;

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
        return craft()->elements->getElementById($this->purchasableId);
    }

    /**
     * @return bool False when no related purchasable exists or order complete.
     */
    public function refreshFromPurchasable()
    {
        if (!$this->getPurchasable() || $this->order->dateOrdered) {
            return false;
        }

        $this->fillFromPurchasable($this->purchasable);

        return true;
    }

    /**
     * @return bool
     */
    public function getOnSale()
    {
        return is_null($this->salePrice) ? false : ($this->salePrice != $this->price);
    }

    /**
     * @return bool
     */
    public function getOnSale()
    {
        craft()->deprecator->log('Market_LineItemModel::underSale():removed', 'You should no longer use `underSale` on the lineItem. Use `onSale`.');
        return $this->getOnSale();
    }

    /**
     * Returns the description from the snapshot of the purchasable
     */
    public function getDescription()
    {
       return $this->snapshot['description'];
    }

    /**
     * Returns the description from the snapshot of the purchasable
     */
    public function getSku()
    {
        return $this->snapshot['sku'];
    }

    /**
     * @param Purchasable $purchasable
     */
    public function fillFromPurchasable(Purchasable $purchasable)
    {
        $this->price = $purchasable->getPrice();

        // Since sales cannot apply to non core purchasables, set to price at default
        $this->salePrice = $purchasable->getPrice();

        $snapshot = [
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getPurchasableId(),
            'cpEditUrl' => '#'
        ];

        // Add our purchasable data to the snapshot, save our sales.
        $this->snapshot = array_merge($purchasable->getSnapShot(), $snapshot);

        if ($purchasable instanceof Market_VariantModel) {

            $this->weight = $purchasable->weight * 1; //converting nulls
            $this->height = $purchasable->height * 1; //converting nulls
            $this->length = $purchasable->length * 1; //converting nulls
            $this->width = $purchasable->width * 1; //converting nulls

            $this->taxCategoryId = $purchasable->product->taxCategoryId;

            $sales = craft()->market_sale->getForVariant($purchasable);

            foreach ($sales as $sale) {
                $this->saleAmount += $sale->calculateTakeoff($this->price);
            }

            // Don't let sale amount be more than the price.
            if (-$this->saleAmount > $this->price) {
                $this->saleAmount = -$this->price;
            }

            // If the product is not promotable but has saleAmount, reset saleAmount to zero
            if (!$purchasable->product->promotable && $this->saleAmount){
                $this->saleAmount = 0;
            }

            $this->salePrice = $this->saleAmount + $this->price;

        } else {
            // Non core commerce purchasables cannot have sales applied (yet)
            $this->saleAmount = 0;
        }


    }

    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
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
            'length' => [
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
            'snapshot' => [AttributeType::Mixed, 'required' => true],
            'note' => AttributeType::Mixed,
            'purchasableId' => AttributeType::Number,
            'orderId' => AttributeType::Number,
            'taxCategoryId' => [AttributeType::Number, 'required' => true],
        ];
    }
}
