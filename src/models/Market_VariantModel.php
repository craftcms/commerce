<?php

namespace Craft;

use Market\Interfaces\Purchasable;

/**
 * Class Market_VariantModel
 *
 * @property int                 id
 * @property int                 productId
 * @property bool                isMaster
 * @property string              sku
 * @property float               price
 * @property float               width
 * @property float               height
 * @property float               length
 * @property float               weight
 * @property int                 stock
 * @property bool                unlimitedStock
 * @property int                 minQty
 * @property int                 maxQty
 *
 * @package Craft
 */
class Market_VariantModel extends BaseElementModel implements Purchasable
{
    protected $elementType = 'Market_Variant';
    public $salePrice;


    public function isEditable()
    {
        return true;
    }

    public function isLocalized()
    {
        return false;
    }

    public function __toString()
    {
        return $this->getContent()->title;
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('market/products/' . $this->product->type->handle . '/' . $this->product->id . '/variants/' . $this->id);
    }

    public function getUrl()
    {
        return $this->product->url.'?variant='.$this->id;
    }

    /**
     * @return bool
     */
    public function getOnSale()
    {
        return is_null($this->salePrice) ? false : ($this->salePrice != $this->price);
    }

    /**
     * @return Market_ProductModel|null
     */
    public function getProduct()
    {
        if ($this->productId) {
            return craft()->market_product->getById($this->productId);
        }

        return null;
    }

    /**
     * @return FieldLayoutModel|null
     */
    public function getFieldLayout()
    {
        if ($this->productId) {
            return craft()->market_productType->getById($this->product->typeId)->asa('variantFieldLayout')->getFieldLayout();
        }

        return null;
    }

    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), [
            'id'             => [AttributeType::Number],
            'productId'      => [AttributeType::Number],
            'isMaster'       => [AttributeType::Bool],
            'sku'            => [AttributeType::String, 'required' => true],
            'price'          => [
                AttributeType::Number,
                'decimals' => 4,
                'required' => true
            ],
            'width'          => [AttributeType::Number, 'decimals' => 4],
            'height'         => [AttributeType::Number, 'decimals' => 4],
            'length'         => [AttributeType::Number, 'decimals' => 4],
            'weight'         => [AttributeType::Number, 'decimals' => 4],
            'stock'          => [AttributeType::Number],
            'unlimitedStock' => [AttributeType::Bool, 'default' => 0],
            'minQty'         => [AttributeType::Number],
            'maxQty'         => [AttributeType::Number]
        ]);
    }

    /**
     * We need to be explicit to meet interface
     * @return mixed
     */
    public function getPrice()
    {
        return $this->attributes['price'];
    }


    /**
     * We need to be explicit to meet interface
     * @return string
     */
    public function getSnapshot()
    {
        $data = [
            'onSale' => $this->getOnSale(),
            'cpEditUrl' => $this->getProduct()->getCpEditUrl()
        ];

        return array_merge($this->getAttributes(),$data);
    }

    /**
     * We need to be explicit to meet interface
     * @return string
     */
    public function getSku()
    {
        return $this->attributes['sku'];
    }

    /**
     * We need to be explicit to meet interface
     * @return string
     */
    public function getDescription()
    {
        return (string) $this->getProduct()->getTitle();
    }

    /**
     * We need to be explicit to meet interface
     * @return int
     */
    public function getPurchasableId()
    {
        return $this->attributes['id'];
    }

    /**
     * Validate based on min and max qty and stock levels.
     *
     * @param Market_LineItemModel $lineItem
     *
     * @return mixed
     */
    public function validateLineItem(Market_LineItemModel $lineItem)
    {

        if (!$this->unlimitedStock && $lineItem->qty > $this->stock) {
            $error = sprintf('There are only %d items left in stock',
                $this->stock);
            $lineItem->addError('qty', $error);
        }

        if ($lineItem->qty < $this->minQty) {
            $error = sprintf('Minimal order qty for this variant is %d',
                $this->minQty);
            $lineItem->addError('qty', $error);
        }

        if ($this->maxQty != 0){
            if ($lineItem->qty > $this->maxQty) {
                $error = sprintf('Maximum order qty for this variant is %d',
                    $this->maxQty);
                $lineItem->addError('qty', $error);
            }
        }


    }

}