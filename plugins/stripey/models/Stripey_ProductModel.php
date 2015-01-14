<?php

namespace Craft;


class Stripey_ProductModel extends BaseElementModel
{

    const LIVE = 'live';
    const PENDING = 'pending';
    const EXPIRED = 'expired';

    protected $elementType = 'Stripey_Product';
    protected $modelRecord = 'Stripey_ProductRecord';
    protected $_variants = null;

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

    public function getCpEditUrl()
    {
        $productType = $this->getProductType();

        return UrlHelper::getCpUrl('stripey/products/' . $productType->handle . '/' . $this->id);
    }

    public function getProductType()
    {
        return craft()->stripey_productType->getProductTypeById($this->typeId);
    }

    public function getType()
    {
        return $this->getProductType();
    }

    public function getStatus()
    {
        $status = parent::getStatus();

        if ($status == static::ENABLED && $this->availableOn) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $availableOn = $this->availableOn->getTimestamp();
            $expiresOn   = ($this->expiresOn ? $this->expiresOn->getTimestamp() : null);

            if ($availableOn <= $currentTime && (!$expiresOn || $expiresOn > $currentTime)) {
                return static::LIVE;
            } else if ($availableOn > $currentTime) {
                return static::PENDING;
            } else {
                return static::EXPIRED;
            }
        }

        return $status;
    }

    private function getVariants()
    {
        if ($this->_variants == null){
            $this->_variants = craft()->stripey_variant->getVariantsByProductId($this->id);
        }
        return $this->_variants;
    }

    public function master(){
        return craft()->stripey_variant->getMasterVariantByProductId($this->id);
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