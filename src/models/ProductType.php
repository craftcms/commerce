<?php
namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;

/**
 * Product type model.
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
class ProductType extends Model
{

    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var bool Has URLs
     */
    public $hasUrls;

    /**
     * @var bool Has dimension
     */
    public $hasDimensions;

    /**
     * @var bool Has variants
     */
    public $hasVariants;

    /**
     * @var bool Has variant title field
     */
    public $hasVariantTitleField = true;

    /**
     * @var string Title format
     */
    public $titleFormat = '{product.title}';

    /**
     * @var string SKU format
     */
    public $skuFormat;

    /**
     * @var string Description format
     */
    public $descriptionFormat;

    /**
     * @var string Line item format
     */
    public $lineItemFormat;

    /**
     * @var string Template
     */
    public $template;

    /**
     * @var  int Field layout ID
     */
    public $fieldLayoutId;

    /**
     * @var int Variant layout ID
     */
    public $variantFieldLayoutId;

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
    public function __toString()
    {
        return $this->handle;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'fieldLayoutId', 'variantLayoutId'], 'number', 'integerOnly' => true],
            [['name', 'handle', 'titleFormat'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [
                ['handle'],
                HandleValidator::class,
                'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']
            ],
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/producttypes/'.$this->id);
    }

    /**
     * @return string
     */
    public function getCpEditVariantUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/producttypes/'.$this->id.'/variant');
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
     * @return \craft\commerce\models\ShippingCategory[]
     */
    public function getShippingCategories($asList = false)
    {
        if (!$this->_shippingCategories) {
            $this->_shippingCategories = craft()->commerce_productTypes->getProductTypeShippingCategories($this->id, 'id');
        }

        if ($asList) {
            return \CHtml::listData($this->_shippingCategories, 'id', 'name');
        }

        return $this->_shippingCategories;
    }

    /**
     * @param int[]|Commerce_ShippingCategoryModel[] $shippingCategories
     */
    public function setShippingCategories($shippingCategories)
    {
        $categories = [];
        foreach ($shippingCategories as $category) {
            if (is_numeric($category)) {
                if ($category = craft()->commerce_shippingCategories->getShippingCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } else {
                if ($category instanceof Commerce_ShippingCategoryModel) {
                    if ($category = craft()->commerce_shippingCategories->getShippingCategoryById($category)) {
                        $categories[$category->id] = $category;
                    }
                }
            }
        }

        $this->_shippingCategories = $categories;
    }

    /**
     * @return Commerce_TaxCategoryModel[]
     */
    public function getTaxCategories($asList = false)
    {
        if (!$this->_taxCategories) {
            $this->_taxCategories = craft()->commerce_productTypes->getProductTypeTaxCategories($this->id, 'id');
        }

        if ($asList) {
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
        foreach ($taxCategories as $category) {
            if (is_numeric($category)) {
                if ($category = craft()->commerce_taxCategories->getTaxCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } else {
                if ($category instanceof Commerce_TaxCategoryModel) {
                    if ($category = craft()->commerce_taxCategories->getTaxCategoryById($category)) {
                        $categories[$category->id] = $category;
                    }
                }
            }
        }

        $this->_taxCategories = $categories;
    }


    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'productFieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Product::class,
                'idAttribute' => 'fieldLayoutId'
            ],
            'variantFieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => Variant::class,
                'idAttribute' => 'variantFieldLayoutId'
            ],
        ];
    }
}
