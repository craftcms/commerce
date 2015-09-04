<?php

namespace Craft;

/**
 * Class Market_ProductTypeModel
 *
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property bool   $hasUrls
 * @property bool   $hasVariants
 * @property string $template
 * @property string $titleFormat
 * @property int    $fieldLayoutId
 * @property int    $variantFieldLayoutId
 * @package Craft
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
 */
class Market_ProductTypeModel extends BaseModel
{

    /**
     * @var
     */
    private $_locales;

    function __toString()
    {
        return Craft::t($this->handle);
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('market/settings/producttypes/' . $this->id);
    }

    public function getCpEditVariantUrl()
    {
        return UrlHelper::getCpUrl('market/settings/producttypes/' . $this->id . '/variant');
    }

    public function getLocales()
    {
        if (!isset($this->_locales))
        {
            if ($this->id)
            {
                $this->_locales = craft()->market_productType->getProductTypeLocales($this->id, 'locale');
            }
            else
            {
                $this->_locales = array();
            }
        }

        return $this->_locales;
    }


    /**
     * Sets the locales on the product type
     *
     * @param $locales
     */
    public function setLocales($locales)
    {
        $this->_locales = $locales;
    }

    public function behaviors()
    {
        return [
            'productFieldLayout' => new FieldLayoutBehavior('Market_Product',
                'fieldLayoutId'),
            'variantFieldLayout' => new FieldLayoutBehavior('Market_Variant',
                'variantFieldLayoutId'),
        ];
    }

    protected function defineAttributes()
    {
        return [
            'id'                   => AttributeType::Number,
            'name'                 => [AttributeType::Name, 'required' => true],
            'handle'               => [AttributeType::Handle, 'required' => true],
            'hasUrls'              => AttributeType::Bool,
            'hasVariants'          => AttributeType::Bool,
            'titleFormat'          => [AttributeType::String, 'required' => true, 'default'=>'{sku}'],
            'template'             => AttributeType::Template,
            'fieldLayoutId'        => AttributeType::Number,
            'variantFieldLayoutId' => AttributeType::Number,
        ];
    }

}