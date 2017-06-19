<?php

namespace craft\commerce\models;

use craft\behaviors\FieldLayoutBehavior;
use craft\behaviors\FieldLayoutTrait;
use craft\commerce\base\Model;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
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
    use FieldLayoutTrait;

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
     * @var TaxCategory[]
     */
    private $_taxCategories;

    /**
     * @var ShippingCategory[]
     */
    private $_shippingCategories;

    /**
     * @var SiteModel[]
     */
    private $_sites;

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
            [['name', 'handle', 'titleFormat'], 'required' => true],
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
    public function getSites()
    {
        if (null === $this->_sites) {
            if ($this->id) {
                $sites = Plugin::getInstance()->getProductTypes()->getProductTypeSites($this->id);
                $this->_sites = [];
                foreach ($sites as $site) {
                    $this->_sites[$site->id] = $site;
                }
            } else {
                $this->_sites = [];
            }
        }

        return $this->_sites;
    }

    /**
     * Sets the sites on the product type
     *
     * @param $sites
     */
    public function setSites($sites)
    {
        $this->_sites = $sites;
    }

    /**
     *
     * @return ShippingCategory[]
     */
    public function getShippingCategories(): array
    {
        if (!$this->_shippingCategories) {
            $this->_shippingCategories = Plugin::getInstance()->getProductTypes()->getShippingCategoriesByProductId($this->id);
        }

        return $this->_shippingCategories;
    }

    /**
     * @param int[]|ShippingCategory[] $shippingCategories
     */
    public function setShippingCategories($shippingCategories)
    {
        if (!is_array($shippingCategories)) {
            return;
        }

        $categories = [];

        foreach ($shippingCategories as $category) {
            if (is_numeric($category)) {
                if ($category = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } else {
                if ($category instanceof ShippingCategory) {
                    if ($category = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($category)) {
                        $categories[$category->id] = $category;
                    }
                }
            }
        }

        $this->_shippingCategories = $categories;
    }

    /**
     * @return TaxCategory[]
     */
    public function getTaxCategories(): array
    {
        if (!$this->_taxCategories) {
            $this->_taxCategories = Plugin::getInstance()->getProductTypes()->getProductTypeTaxCategories($this->id, 'id');
        }

        return $this->_taxCategories;
    }

    /**
     * @param int[]|TaxCategory[] $taxCategories
     */
    public function setTaxCategories($taxCategories)
    {
        if (!is_array($taxCategories)) {
            return;
        }

        $categories = [];

        foreach ($taxCategories as $category) {
            if (is_numeric($category)) {
                if ($category = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } else {
                if ($category instanceof TaxCategory) {
                    if ($category = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($category)) {
                        $categories[$category->id] = $category;
                    }
                }
            }
        }

        $this->_taxCategories = $categories;
    }


    /**
     * @return mixed
     */
    public function getProductFieldLayout()
    {
        return $this->getBehavior('productFieldLayout')->getFieldLayout();
    }

    /**
     * @return mixed
     */
    public function getVariantFieldLayout()
    {
        return $this->getBehavior('variantFieldLayout')->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
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
