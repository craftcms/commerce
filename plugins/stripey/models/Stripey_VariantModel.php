<?php

namespace Craft;


class Stripey_VariantModel extends BaseModel
{

    protected $modelRecord = 'Stripey_VariantRecord';

    public function isLocalized()
    {
        return false;
    }

    public function __toString()
    {
        return $this->sku;
    }

    public function getCpEditUrl()
    {
        $product = $this->getProduct();
        return UrlHelper::getCpUrl('stripey/products/' . $product->productType->handle .'/'.$product->id.'/variants/' . $this->id);
    }

    public function getProduct()
    {
        return craft()->stripey_product->getProductById($this->productId);
    }

    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), array(
            'id'        => AttributeType::Number,
            'productId' => AttributeType::Number,
            'isMaster'  => AttributeType::Bool,
            'sku'       => array(AttributeType::String, 'required' => true),
            'price'     => array(AttributeType::Number, 'decimals' => 4, 'required' => true),
            'width'     => array(AttributeType::Number, 'decimals' => 4),
            'height'    => array(AttributeType::Number, 'decimals' => 4),
            'length'    => array(AttributeType::Number, 'decimals' => 4),
            'weight'    => array(AttributeType::Number, 'decimals' => 4),
            'stock'     => array(AttributeType::Number),
            'deletedAt' => array(AttributeType::DateTime)
        ));
    }
}