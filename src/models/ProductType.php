<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\base\Model;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\fieldlayoutelements\VariantsField;
use craft\commerce\Plugin;
use craft\commerce\records\ProductType as ProductTypeRecord;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

/**
 * Product type model.
 * @method null setFieldLayout(FieldLayout $fieldLayout)
 * @method FieldLayout getFieldLayout()
 *
 * @property string $cpEditUrl
 * @property string $cpEditVariantUrl
 * @property FieldLayout $fieldLayout
 * @property mixed $productFieldLayout
 * @property array|ShippingCategory[]|int[] $shippingCategories
 * @property ProductTypeSite[] $siteSettings the product types' site-specific settings
 * @property TaxCategory[]|array|int[] $taxCategories
 * @property mixed $variantFieldLayout
 * @mixin FieldLayoutBehavior
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductType extends Model
{
    /**
     * @var int ID
     */
    public int $id;

    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Handle
     */
    public string $handle;

    /**
     * @var bool Has dimension
     */
    public bool $hasDimensions;

    /**
     * @var bool Has variants
     */
    public bool $hasVariants;

    /**
     * @var bool Has variant title field
     */
    public bool $hasVariantTitleField = true;

    /**
     * @var string Variant title format
     * TODO: Rename to variantTitleFormat in 4.0 #COM-44
     */
    public string $titleFormat = '{product.title}';

    /**
     * @var bool Has product title field?
     */
    public bool $hasProductTitleField = true;

    /**
     * @var string Product title format
     */
    public string $productTitleFormat = '';

    /**
     * @var string SKU format
     */
    public string $skuFormat;

    /**
     * @var string Description format
     */
    public string $descriptionFormat;

    /**
     * @var string Line item format
     */
    public string $lineItemFormat;

    /**
     * @var string Template
     */
    public string $template;

    /**
     * @var int Field layout ID
     */
    public int $fieldLayoutId;

    /**
     * @var int|null Variant layout ID
     */
    public ?int $variantFieldLayoutId;

    /**
     * @var string UID
     */
    public string $uid;

    /**
     * @var TaxCategory[]
     */
    private array $_taxCategories;

    /**
     * @var ShippingCategory[]
     */
    private array $_shippingCategories;

    /**
     * @var ProductTypeSite[]
     */
    private array $_siteSettings;

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
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['id', 'fieldLayoutId', 'variantFieldLayoutId'], 'number', 'integerOnly' => true];
        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [
            ['titleFormat'], 'required', 'when' => static function($model) {
                /** @var static $model */
                return !$model->hasVariantTitleField && $model->hasVariants;
            }
        ];
        $rules[] = [
            ['productTitleFormat'], 'required', 'when' => static function($model) {
                /** @var static $model */
                return !$model->hasProductTitleField;
            }
        ];
        $rules[] = [['name', 'handle', 'descriptionFormat'], 'string', 'max' => 255];
        $rules[] = [['handle'], UniqueValidator::class, 'targetClass' => ProductTypeRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'];
        $rules[] = [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']];

        $rules[] = ['fieldLayout', 'validateFieldLayout'];
        $rules[] = ['variantFieldLayout', 'validateVariantFieldLayout'];

        return $rules;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/producttypes/' . $this->id);
    }

    /**
     * @return string
     */
    public function getCpEditVariantUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/producttypes/' . $this->id . '/variant');
    }

    /**
     * Returns the product type's site-specific settings.
     *
     * @return ProductTypeSite[]
     */
    public function getSiteSettings(): array
    {
        if (isset($this->_siteSettings)) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        $this->setSiteSettings(ArrayHelper::index(Plugin::getInstance()->getProductTypes()->getProductTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    /**
     * Sets the product type's site-specific settings.
     *
     * @param ProductTypeSite[] $siteSettings
     */
    public function setSiteSettings(array $siteSettings): void
    {
        $this->_siteSettings = $siteSettings;

        foreach ($this->_siteSettings as $settings) {
            $settings->setProductType($this);
        }
    }

    /**
     * @return ShippingCategory[]
     */
    public function getShippingCategories(): array
    {
        if ($this->_shippingCategories === null) {
            $this->_shippingCategories = Plugin::getInstance()->getShippingCategories()->getShippingCategoriesByProductTypeId($this->id);
        }

        return $this->_shippingCategories;
    }

    /**
     * @param int[]|ShippingCategory[] $shippingCategories
     */
    public function setShippingCategories(array $shippingCategories): void
    {
        $categories = [];
        foreach ($shippingCategories as $category) {
            if (is_numeric($category)) {
                if ($category = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } else if ($category instanceof ShippingCategory) {
                // Make sure it exists
                if ($category = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($category->id)) {
                    $categories[$category->id] = $category;
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
        if ($this->_taxCategories === null) {
            $this->_taxCategories = Plugin::getInstance()->getTaxCategories()->getTaxCategoriesByProductTypeId($this->id);
        }

        return $this->_taxCategories;
    }

    /**
     * @param int[]|TaxCategory[] $taxCategories
     */
    public function setTaxCategories(array $taxCategories): void
    {
        $categories = [];
        foreach ($taxCategories as $category) {
            if (is_numeric($category)) {
                if ($category = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } else {
                if ($category instanceof TaxCategory) {
                    // Make sure it exists.
                    if ($category = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($category->id)) {
                        $categories[$category->id] = $category;
                    }
                }
            }
        }

        $this->_taxCategories = $categories;
    }

    /**
     * @return FieldLayout
     */
    public function getProductFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('productFieldLayout');
        $fieldLayout = $behavior->getFieldLayout();

        // If this product type has variants, make sure the Variants field is in the layout somewhere
        if ($this->hasVariants && !$fieldLayout->isFieldIncluded('variants')) {
            $layoutTabs = $fieldLayout->getTabs();
            $variantTabName = Craft::t('commerce', 'Variants');
            if (ArrayHelper::contains($layoutTabs, 'name', $variantTabName)) {
                $variantTabName .= ' ' . StringHelper::randomString(10);
            }
            $layoutTabs[] = new FieldLayoutTab([
                'name' => $variantTabName,
                'elements' => [
                    [
                        'type' => VariantsField::class,
                    ],
                ],
            ]);
            $fieldLayout->setTabs($layoutTabs);
        }

        return $fieldLayout;
    }

    /**
     * Validate the field layout to make sure no fields with reserved words are used.
     *
     * @since 3.4
     */
    public function validateFieldLayout(): void
    {
        $fieldLayout = $this->getFieldLayout();

        $fieldLayout->reservedFieldHandles = [
            'cheapestVariant',
            'defaultVariant',
            'variants',
        ];

        if (!$fieldLayout->validate()) {
            $this->addModelErrors($fieldLayout, 'fieldLayout');
        }
    }

    /**
     * Validate the variant field layout to make sure no fields with reserved words are used.
     *
     * @since 3.4
     */
    public function validateVariantFieldLayout(): void
    {
        $variantFieldLayout = $this->getVariantFieldLayout();

        $variantFieldLayout->reservedFieldHandles = [
            'description',
            'price',
            'product',
            'sku',
        ];

        if (!$variantFieldLayout->validate()) {
            $this->addModelErrors($variantFieldLayout, 'variantFieldLayout');
        }
    }

    /**
     * @return FieldLayout
     */
    public function getVariantFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('variantFieldLayout');
        return $behavior->getFieldLayout();
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
