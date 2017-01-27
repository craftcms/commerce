<?php
namespace Craft;

/**
 * Product type model.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property bool $hasUrls
 * @property bool $hasDimensions
 * @property bool $hasVariants
 * @property bool $hasVariantTitleField
 * @property string $template
 * @property string $titleFormat
 * @property string $skuFormat
 * @property string $descriptionFormat
 * @property int $fieldLayoutId
 * @property int $variantFieldLayoutId
 *
 * @method null setFieldLayout(FieldLayoutModel $fieldLayout)
 * @method FieldLayoutModel getFieldLayout()
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_ProductTypeModel extends BaseModel
{

    /**
     * @var Commerce_TaxCategoryModel[]
     */
    private $_taxCategories;

    /**
     * @var Commerce_ShippingCategoryModel[]
     */
    private $_shippingCategories;

    /**
     * @var LocaleModel[]
     */
    private $_locales;

    /**
     * @return null|string
     */
    function __toString()
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/producttypes/' . $this->id);
    }

    /**
     * @return string
     */
    public function getCpEditVariantUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/producttypes/' . $this->id . '/variant');
    }

    /**
     * @return array
     */
    public function getLocales()
    {
        if (!isset($this->_locales)) {
            if ($this->id) {
                $this->_locales = craft()->commerce_productTypes->getProductTypeLocales($this->id, 'locale');
            } else {
                $this->_locales = [];
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

    /**
     * @return Commerce_ShippingCategoryModel[]
     */
    public function getShippingCategories($asList = false)
    {
        if (!$this->_shippingCategories)
        {
            $this->_shippingCategories =  craft()->commerce_productTypes->getProductTypeShippingCategories($this->id,'id');
        }

        if ($asList)
        {
            return \CHtml::listData($this->_shippingCategories, 'id', 'name');
        }

        return $this->_shippingCategories;
    }

    /**
     * @return Commerce_TaxCategoryModel[]
     */
    public function getTaxCategories($asList = false)
    {
        if (!$this->_taxCategories)
        {
            $this->_taxCategories =  craft()->commerce_productTypes->getProductTypeTaxCategories($this->id,'id');
        }

        if ($asList)
        {
            return \CHtml::listData($this->_taxCategories, 'id', 'name');
        }

        return $this->_taxCategories;
    }

    /**
     * @param int[]|Commerce_TaxCategoryModel[] $taxCategories
     */
    public function setTaxCategories($taxCategories)
    {
        $categories = [];
        foreach ($taxCategories as $category)
        {
            if (is_numeric($category))
            {
                if($category = craft()->commerce_taxCategories->getTaxCategoryById($category))
                {
                    $categories[$category->id] = $category;
                }
            }else{
                if($category instanceof Commerce_TaxCategoryModel)
                {
                    if($category = craft()->commerce_taxCategories->getTaxCategoryById($category))
                    {
                        $categories[$category->id] = $category;
                    }
                }
            }
        }

        $this->_taxCategories = $categories;
    }

    /**
     * @param int[]|Commerce_ShippingCategoryModel[] $shippingCategories
     */
    public function setShippingCategories($shippingCategories)
    {
        $categories = [];
        foreach ($shippingCategories as $category)
        {
            if (is_numeric($category))
            {
                if($category = craft()->commerce_shippingCategories->getShippingCategoryById($category))
                {
                    $categories[$category->id] = $category;
                }
            }else{
                if($category instanceof Commerce_ShippingCategoryModel)
                {
                    if($category = craft()->commerce_shippingCategories->getShippingCategoryById($category))
                    {
                        $categories[$category->id] = $category;
                    }
                }
            }
        }

        $this->_shippingCategories = $categories;
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            'productFieldLayout' => new FieldLayoutBehavior('Commerce_Product',
                'fieldLayoutId'),
            'variantFieldLayout' => new FieldLayoutBehavior('Commerce_Variant',
                'variantFieldLayoutId'),
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => [AttributeType::Name, 'required' => true],
            'handle' => [AttributeType::Handle, 'required' => true],
            'hasUrls' => AttributeType::Bool,
            'hasDimensions' => AttributeType::Bool,
            'hasVariants' => AttributeType::Bool,
            'hasVariantTitleField' => [AttributeType::Bool,'default' => 1],
            'titleFormat' => [AttributeType::String, 'required' => true, 'default' => '{product.title}'],
            'skuFormat' => AttributeType::String,
            'descriptionFormat' => AttributeType::String,
            'lineItemFormat' => AttributeType::String,
            'template' => AttributeType::Template,
            'fieldLayoutId' => AttributeType::Number,
            'variantFieldLayoutId' => AttributeType::Number,
        ];
    }

}
