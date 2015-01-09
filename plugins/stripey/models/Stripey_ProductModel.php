<?php

namespace Craft;


class Stripey_ProductModel extends BaseElementModel
{
    protected $elementType = 'Stripey_Product';
    protected $modelRecord = 'Stripey_ProductRecord';

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
        return $this->title;
    }

//    public function getType()
//    {
//        return $this->getProductType();
//    }

    public function getCpEditUrl()
    {
        $productType = $this->getProductType();

        return UrlHelper::getCpUrl('stripey/products/' . $productType->handle . '/' . $this->id);
    }

    public function getProductType()
    {
        return craft()->stripey_productType->getProductTypeById($this->typeId);
    }

    public function getFieldLayout()
    {
        if ($this->getProductType()) {
            return $this->productType->getFieldLayout();
        }
    }

    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), array(
            'typeId'      => AttributeType::Number,
            'authorId'    => AttributeType::Number,
            'availableOn' => AttributeType::DateTime,
            'expiresOn'   => AttributeType::DateTime,
        ));
    }
}