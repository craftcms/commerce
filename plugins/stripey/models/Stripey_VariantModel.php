<?php

namespace Craft;

/**
 * Class Stripey_VariantModel
 * @property int id
 * @property int productId
 * @property bool isMaster
 * @property string sku
 * @property float price
 * @property float width
 * @property float height
 * @property float length
 * @property float weight
 * @property float stock
 * @property DateTime deletedAt
 * @package Craft
 */
class Stripey_VariantModel extends BaseModel
{
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
        return craft()->stripey_product->getById($this->productId);
    }

    /**
     * @param int $optionTypeId
     * @return Stripey_OptionValueModel
     */
    public function getOptionValue($optionTypeId)
    {
        $optionValue = Stripey_OptionValueRecord::model()->find(array(
            'join' => 'JOIN craft_stripey_variant_optionvalues v ON v.optionValueId = t.id',
            'condition' => 'v.variantId = :v AND t.optionTypeId = :ot',
            'params' => array('v' => $this->id, 'ot' => $optionTypeId),
        ));

        return Stripey_OptionValueModel::populateModel($optionValue);
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