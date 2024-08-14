<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\base\Field;
use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\base\Model;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\fieldlayoutelements\VariantsField;
use craft\commerce\Plugin;
use craft\commerce\records\ProductType as ProductTypeRecord;
use craft\enums\PropagationMethod;
use craft\errors\DeprecationException;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;

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
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var bool Whether versioning should be enabled for this product type.
     * @since 5.0.0
     */
    public bool $enableVersioning = false;

    /**
     * @var bool Has dimension
     */
    public bool $hasDimensions = false;

    /**
     * @var int|null Maximum number of variants
     */
    public ?int $maxVariants = null;

    /**
     * @var bool Has variant title field
     */
    public bool $hasVariantTitleField = true;

    /**
     * @var string Variant title format
     */
    public string $variantTitleFormat = '{product.title}';

    /**
     * @var string Variant title translation method
     * @phpstan-var Field::TRANSLATION_METHOD_NONE|Field::TRANSLATION_METHOD_SITE|Field::TRANSLATION_METHOD_SITE_GROUP|Field::TRANSLATION_METHOD_LANGUAGE|Field::TRANSLATION_METHOD_CUSTOM
     * @since 5.1.0
     */
    public string $variantTitleTranslationMethod = Field::TRANSLATION_METHOD_SITE;

    /**
     * @var string|null Variant title translation key format
     * @since 5.1.0
     */
    public ?string $variantTitleTranslationKeyFormat = null;

    /**
     * @var bool Has product title field?
     */
    public bool $hasProductTitleField = true;

    /**
     * @var string Product title format
     */
    public string $productTitleFormat = '';

    /**
     * @var string Product title translation method
     * @phpstan-var Field::TRANSLATION_METHOD_NONE|Field::TRANSLATION_METHOD_SITE|Field::TRANSLATION_METHOD_SITE_GROUP|Field::TRANSLATION_METHOD_LANGUAGE|Field::TRANSLATION_METHOD_CUSTOM
     * @since 5.1.0
     */
    public string $productTitleTranslationMethod = Field::TRANSLATION_METHOD_SITE;

    /**
     * @var string|null Product title translation key format
     * @since 5.1.0
     */
    public ?string $productTitleTranslationKeyFormat = null;

    /**
     * @var string|null SKU format
     */
    public ?string $skuFormat = null;

    /**
     * @var string Description format
     */
    public string $descriptionFormat = '{product.title} - {title}';

    /**
     * @var string|null Template
     */
    public ?string $template = null;

    /**
     * @var int|null Field layout ID
     */
    public ?int $fieldLayoutId = null;

    /**
     * @var int|null Variant layout ID
     */
    public ?int $variantFieldLayoutId = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @var TaxCategory[]|null
     */
    private ?array $_taxCategories = null;

    /**
     * @var ShippingCategory[]|null
     */
    private ?array $_shippingCategories = null;

    /**
     * @var ProductTypeSite[]|null
     */
    private ?array $_siteSettings = null;

    /**
     * @var PropagationMethod Propagation method
     *
     * This will be set to one of the following:
     *
     *  - [[PropagationMethod::None]] – Only save products in the site they were created in
     *  - [[PropagationMethod::SiteGroup]] – Save  products to other sites in the same site group
     *  - [[PropagationMethod::Language]] – Save products to other sites with the same language
     *  - [[PropagationMethod::Custom]] – Save products to other sites based on a custom [[$propagationKeyFormat|propagation key format]]
     *  - [[PropagationMethod::All]] – Save products to all sites supported by the owner element
     *
     * @since 5.1.0
     */
    public PropagationMethod $propagationMethod = PropagationMethod::All;

    /**
     * @return null|string
     */
    public function __toString()
    {
        return (string)$this->handle;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['id', 'fieldLayoutId', 'variantFieldLayoutId'], 'number', 'integerOnly' => true],
            [['name', 'handle'], 'required'],
            [
                ['variantTitleFormat'],
                'required',
                'when' => static function($model) {
                    /** @var static $model */
                    return !$model->hasVariantTitleField;
                },
            ],
            [
                ['productTitleFormat'],
                'required',
                'when' => static function($model) {
                    /** @var static $model */
                    return !$model->hasProductTitleField;
                },
            ],
            [['name', 'handle', 'descriptionFormat'], 'string', 'max' => 255],
            [['handle'], UniqueValidator::class, 'targetClass' => ProductTypeRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
            [['maxVariants'], 'integer', 'min' => 1],
            ['fieldLayout', 'validateFieldLayout'],
            ['variantFieldLayout', 'validateVariantFieldLayout'],
            ['siteSettings', 'required', 'message' => Craft::t('commerce','At least one site must be enabled for the product type.')],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/producttypes/' . $this->id);
    }

    public function getCpEditVariantUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/producttypes/' . $this->id . '/variant');
    }

    /**
     * Returns the site IDs that are enabled for the product type.
     *
     * @return int[]
     * @since 5.1.0
     */
    public function getSiteIds(): array
    {
        return array_keys($this->getSiteSettings());
    }

    /**
     * Returns the product type's site-specific settings.
     *
     * @return ProductTypeSite[]
     * @throws InvalidConfigException
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
     * @throws InvalidConfigException
     */
    public function getShippingCategories(): array
    {
        if ($this->_shippingCategories === null && $this->id) {
            $this->_shippingCategories = Plugin::getInstance()->getShippingCategories()->getShippingCategoriesByProductTypeId($this->id);
        }

        return $this->_shippingCategories ?? [];
    }

    /**
     * @param int[]|ShippingCategory[] $shippingCategories
     * @throws InvalidConfigException
     */
    public function setShippingCategories(array $shippingCategories): void
    {
        $categories = [];
        foreach ($shippingCategories as $category) {
            if (is_numeric($category)) {
                if ($category = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } elseif ($category instanceof ShippingCategory) {
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
     * @throws InvalidConfigException
     */
    public function getTaxCategories(): array
    {
        if ($this->_taxCategories === null && $this->id) {
            $this->_taxCategories = Plugin::getInstance()->getTaxCategories()->getTaxCategoriesByProductTypeId($this->id);
        }

        return $this->_taxCategories ?? [];
    }

    /**
     * @param int[]|TaxCategory[] $taxCategories
     * @throws InvalidConfigException
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
     * @throws InvalidConfigException
     */
    public function getProductFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('productFieldLayout');
        $fieldLayout = $behavior->getFieldLayout();

        // If this product type has variants, make sure the Variants field is in the layout somewhere
        if (!$fieldLayout->isFieldIncluded('variants')) {
            $layoutTabs = $fieldLayout->getTabs();
            $variantTabName = Craft::t('commerce', 'Variants');
            if (ArrayHelper::contains($layoutTabs, 'name', $variantTabName)) {
                $variantTabName .= ' ' . StringHelper::randomString(10);
            }

            $contentTab = new FieldLayoutTab();
            $contentTab->setLayout($fieldLayout);
            $contentTab->name = $variantTabName;
            $contentTab->setElements([
                ['type' => VariantsField::class],
            ]);

            $layoutTabs[] = $contentTab;
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
            'availableForPurchase',
            'description',
            'freeShipping',
            'hasUnlimitedStock',
            'height',
            'length',
            'maxQty',
            'minQty',
            'price',
            'product',
            'promotable',
            'promotionalPrice',
            'sku',
            'stock',
            'weight',
            'width',
        ];

        if (!$variantFieldLayout->validate()) {
            $this->addModelErrors($variantFieldLayout, 'variantFieldLayout');
        }
    }

    /**
     * @throws InvalidConfigException
     */
    public function getVariantFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('variantFieldLayout');
        return $behavior->getFieldLayout();
    }

    /**
     * @return string
     * @deprecated 4.0.0
     */
    public function getTitleFormat(): string
    {
        Craft::$app->getDeprecator()->log('craft\commerce\models\ProductType::titleFormat', 'Getting `ProductType::titleFormat` has been deprecate. Use `ProductType::variantTitleFormat` instead.');
        return $this->variantTitleFormat;
    }

    /**
     * @param string $titleFormat
     * @return void
     * @throws DeprecationException
     * @deprecated 4.0.0
     */
    public function setTitleFormat(string $titleFormat): void
    {
        Craft::$app->getDeprecator()->log('craft\commerce\models\ProductType::titleFormat', 'Setting `ProductType::titleFormat` has been deprecate. Use `ProductType::variantTitleFormat` instead.');
        $this->variantTitleFormat = $titleFormat;
    }

    /**
     * @return bool
     * @deprecated 5.0.0
     */
    public function getHasVariants(): bool
    {
        Craft::$app->getDeprecator()->log('craft\commerce\models\ProductType::hasVariants', 'Use `ProductType::maxVariants > 1` instead.');
        return $this->maxVariants > 1;
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['productFieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Product::class,
            'idAttribute' => 'fieldLayoutId',
        ];

        $behaviors['variantFieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Variant::class,
            'idAttribute' => 'variantFieldLayoutId',
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'taxCategories';
        $fields[] = 'shippingCategories';
        $fields[] = 'siteSettings';

        return $fields;
    }
}
