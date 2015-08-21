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
        if ($purchasable = craft()->elements->getElementById($this->purchasableId)) {
            return $purchasable;
        }

        // If there is no purchasable it's probably been deleted. Give them them a snapshot instance.
        if(isset($this->snapshot['className'])) {
            $className = $this->snapshot['className'];
            if (class_exists($className)){
                $dummyPurchasable = $className::populateModel($this->snapshot);
                if($dummyPurchasable){
                    return $dummyPurchasable;
                }
            }
        }

        // Try to send them something about the item purchased.
        if ($this->snapshot){
            return $this->snapshot;
        }

        // If we can't even send them a snapshot instance, then we have bigger problems.
        throw new Exception('Cannot find the purchasable on line item on order, deleted without a snapshot');
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
        $this->price = $purchasable->getPrice();
        $snapshot = [
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getPurchasableId(),
            'className' => $purchasable->getModelClass(),
            'cpEditUrl' => '#'
        ];

        $this->snapshot = array_merge($purchasable->getSnapShot(), $snapshot);

        if ($purchasable instanceof Market_VariantModel || $purchasable instanceof Market_ProductModel) {

            $this->weight = $purchasable->weight * 1; //converting nulls
            $this->height = $purchasable->height * 1; //converting nulls
            $this->length = $purchasable->length * 1; //converting nulls
            $this->width = $purchasable->width * 1; //converting nulls

            $this->taxCategoryId = $purchasable->product->taxCategoryId;

            $sales = craft()->market_sale->getForVariant($purchasable);

            foreach ($sales as $sale) {
                $this->saleAmount += $sale->calculateTakeoff($this->price);
            }

            if ($this->saleAmount > $this->price) {
                $this->saleAmount = $this->price;
            }

            $snapshotMore = [
              'onSale' => $purchasable->getOnSale(),
              'cpEditUrl' => $purchasable->getCpEditUrl()
            ];

            $this->snapshot = array_merge($this->snapshot, $snapshotMore);

        } else {
            $this->saleAmount = $this->price;
        }

    }

    public function toArray()
    {
        $data = [];
        foreach ($this->defineAttributes() as $key => $val) {
            $data[$key] = $this->getAttribute($key, true);
        }
        return $data;
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