<?php

namespace Stripey\Product;

use Craft\BaseElementModel;
use Craft\Stripey_ProductRecord as ProductRecord;

class Creator
{
    /** @var \BaseElementModel $_charge */
    private $_product;

    private $_isNewProduct;

    function __construct()
    {

    }


    public function save(BaseElementModel $product)
    {
        $this->_product      = $product;
        $this->_isNewProduct = !$product->id;

        if ($this->_isNewProduct) {
            return $this->createNewProduct();
        } else {
            return $this->saveProduct();
        }
    }

    private function createNewProduct()
    {

        $productRecord = new ProductRecord();
        $productRecord->availableOn = $this->_product->availableOn;
        $productRecord->expiresOn   = $this->_product->expiresOn;
        $productRecord->typeId      = $this->_product->typeId;

        $productRecord->validate();

        $this->_product->addErrors($productRecord->getErrors());

        if (!$this->_product->hasErrors()) {
            if (\Craft\craft()->elements->saveElement($this->_product)) {
                $productRecord->id = $this->_product->id;
                $productRecord->save(false);
                return true;
            }
        }
        return false;
    }

    private function saveProduct()
    {
        $productRecord = ProductRecord::model()->findById($this->_product->id);

        if (!$productRecord) {
            throw new Exception(Craft::t('No product exists with the ID “{id}”', array('id' => $this->_product->id)));
        }

        if (\Craft\craft()->elements->saveElement($this->_product)) {
            $productRecord->availableOn = $this->_product->availableOn;
            $productRecord->expiresOn   = $this->_product->expiresOn;
            $productRecord->typeId      = $this->_product->typeId;
            $productRecord->save();

            return true;
        }

        return false;
    }


}