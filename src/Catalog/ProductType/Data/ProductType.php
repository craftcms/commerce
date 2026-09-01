<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\ProductType\Data;

use craft\helpers\Db;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Enums\PropagationMethod;
use CraftCms\Cms\FieldLayout\Contracts\FieldLayoutProviderInterface;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\FieldLayoutTab;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Catalog\Data\ProductTypeSite;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\FieldLayoutElements\VariantsField;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Catalog\ProductType\Validation\ProductTypeRules;
use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Validation\Validator;
use function CraftCms\Cms\t;

/**
 * Product type. Needs two independent field layouts (Product's custom
 * fields and Variant's custom fields) — {@see \CraftCms\Cms\FieldLayout\Concerns\HasFieldLayout}
 * only supports one field layout per host class, so both getter/setter
 * pairs below are hand-rolled rather than using the trait.
 */
#[Ruleset(ProductTypeRules::class)]
class ProductType extends Component implements FieldLayoutProviderInterface
{
    public const DEFAULT_PLACEMENT_BEGINNING = 'beginning';

    public const DEFAULT_PLACEMENT_END = 'end';

    public ?int $id = null;

    public ?string $name = null;

    public ?string $handle = null;

    public bool $enableVersioning = false;

    public bool $hasDimensions = false;

    public ?int $maxVariants = null;

    public bool $hasVariantTitleField = true;

    public string $variantTitleFormat = '{product.title}';

    public string $variantUiLabelFormat = '{title}';

    /** @phpstan-var 'none'|'site'|'siteGroup'|'language'|'custom' */
    public string $variantTitleTranslationMethod = 'site';

    public ?string $variantTitleTranslationKeyFormat = null;

    public bool $hasProductTitleField = true;

    public string $productTitleFormat = '';

    public string $productUiLabelFormat = '{title}';

    /** @phpstan-var 'none'|'site'|'siteGroup'|'language'|'custom' */
    public string $productTitleTranslationMethod = 'site';

    public ?string $productTitleTranslationKeyFormat = null;

    public bool $showSlugField = true;

    /** @phpstan-var 'none'|'site'|'siteGroup'|'language'|'custom' */
    public string $slugTranslationMethod = 'site';

    public ?string $slugTranslationKeyFormat = null;

    public ?string $skuFormat = null;

    public string $descriptionFormat = '{product.title} - {title}';

    public ?string $template = null;

    public bool $isStructure = false;

    public ?int $maxLevels = null;

    /** @phpstan-var self::DEFAULT_PLACEMENT_BEGINNING|self::DEFAULT_PLACEMENT_END */
    public string $defaultPlacement = self::DEFAULT_PLACEMENT_END;

    public ?int $structureId = null;

    public ?int $fieldLayoutId = null;

    public ?int $variantFieldLayoutId = null;

    public ?string $uid = null;

    public ?array $previewTargets = null;

    /** Whether {@see ProductTypeRules} should validate the handle for uniqueness. */
    public bool $validateHandleUniqueness = true;

    public PropagationMethod $propagationMethod = PropagationMethod::All;

    /** @var TaxCategory[]|null */
    private ?array $_taxCategories = null;

    /** @var ShippingCategory[]|null */
    private ?array $_shippingCategories = null;

    /** @var ProductTypeSite[]|null */
    private ?array $_siteSettings = null;

    private ?FieldLayout $_productFieldLayout = null;

    private ?FieldLayout $_variantFieldLayout = null;

    public function __construct(array|object $config = [])
    {
        parent::__construct($config);

        if (!isset($this->previewTargets)) {
            $this->previewTargets = [
                [
                    'label' => t('Primary {type} page', [
                        'type' => Product::lowerDisplayName(),
                    ], category: 'app'),
                    'urlFormat' => '{url}',
                ],
            ];
        }

        if ($this->productTitleTranslationKeyFormat === '') {
            $this->productTitleTranslationKeyFormat = null;
        }

        if ($this->variantTitleTranslationKeyFormat === '') {
            $this->variantTitleTranslationKeyFormat = null;
        }

        if ($this->slugTranslationKeyFormat === '') {
            $this->slugTranslationKeyFormat = null;
        }
    }

    public function __toString(): string
    {
        return (string)$this->handle;
    }

    public function getHandle(): ?string
    {
        return $this->handle;
    }

    #[\Override]
    public function afterValidate(?Validator $validator = null): void
    {
        $this->validateFieldLayout();
        $this->validateVariantFieldLayout();
        $this->validatePreviewTargets();

        if (empty($this->getSiteSettings())) {
            $this->errors()->add('siteSettings', t('At least one site must be enabled for the product type.', category: 'commerce'));
        }
    }

    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/settings/producttypes/' . $this->id);
    }

    public function getCpEditVariantUrl(): string
    {
        return Url::cpUrl('commerce/settings/producttypes/' . $this->id . '/variant');
    }

    /**
     * @return int[]
     */
    public function getSiteIds(): array
    {
        return array_keys($this->getSiteSettings());
    }

    /**
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

        $this->setSiteSettings(Arr::keyBy(app(ProductTypes::class)->getProductTypeSites($this->id), 'siteId'));

        return $this->_siteSettings;
    }

    /**
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
        if ($this->_shippingCategories === null && $this->id) {
            $this->_shippingCategories = app(ShippingCategories::class)->getShippingCategoriesByProductTypeId($this->id);
        }

        return $this->_shippingCategories ?? [];
    }

    /**
     * @param int[]|ShippingCategory[] $shippingCategories
     */
    public function setShippingCategories(array $shippingCategories): void
    {
        $categories = [];
        foreach ($shippingCategories as $category) {
            if (is_numeric($category)) {
                if ($category = app(ShippingCategories::class)->getShippingCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } elseif ($category instanceof ShippingCategory) {
                if ($category = app(ShippingCategories::class)->getShippingCategoryById($category->id)) {
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
        if ($this->_taxCategories === null && $this->id) {
            $this->_taxCategories = app(TaxCategories::class)->getTaxCategoriesByProductTypeId($this->id);
        }

        return $this->_taxCategories ?? [];
    }

    /**
     * @param int[]|TaxCategory[] $taxCategories
     */
    public function setTaxCategories(array $taxCategories): void
    {
        $categories = [];
        foreach ($taxCategories as $category) {
            if (is_numeric($category)) {
                if ($category = app(TaxCategories::class)->getTaxCategoryById($category)) {
                    $categories[$category->id] = $category;
                }
            } elseif ($category instanceof TaxCategory) {
                if ($category = app(TaxCategories::class)->getTaxCategoryById($category->id)) {
                    $categories[$category->id] = $category;
                }
            }
        }

        $this->_taxCategories = $categories;
    }

    public function getFieldLayout(): FieldLayout
    {
        return $this->getProductFieldLayout();
    }

    public function getProductFieldLayout(): FieldLayout
    {
        if (isset($this->_productFieldLayout)) {
            return $this->_productFieldLayout;
        }

        $fieldLayout = $this->_resolveFieldLayout($this->fieldLayoutId, Product::class);

        // If this product type has variants, make sure the Variants field is in the layout somewhere
        if (!$fieldLayout->isFieldIncluded('variants')) {
            $layoutTabs = $fieldLayout->getTabs();
            $variantTabName = t('Variants', category: 'commerce');
            if (Arr::contains($layoutTabs, 'name', $variantTabName)) {
                $variantTabName .= ' ' . Str::random(10);
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

        return $this->_productFieldLayout = $fieldLayout;
    }

    public function setProductFieldLayout(FieldLayout $fieldLayout): void
    {
        $this->_productFieldLayout = $fieldLayout;
    }

    public function getVariantFieldLayout(): FieldLayout
    {
        if (isset($this->_variantFieldLayout)) {
            return $this->_variantFieldLayout;
        }

        return $this->_variantFieldLayout = $this->_resolveFieldLayout($this->variantFieldLayoutId, Variant::class);
    }

    public function setVariantFieldLayout(FieldLayout $fieldLayout): void
    {
        $this->_variantFieldLayout = $fieldLayout;
    }

    /**
     * @param class-string $elementType
     */
    private function _resolveFieldLayout(?int $id, string $elementType): FieldLayout
    {
        if ($id) {
            $fieldLayout = Fields::getLayoutById($id, true);
            if (!$fieldLayout) {
                throw new \RuntimeException('Invalid field layout ID: ' . $id);
            }
        } else {
            $fieldLayout = new FieldLayout([
                'type' => $elementType,
            ]);
        }

        $fieldLayout->provider = $this;

        return $fieldLayout;
    }

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

    public function validatePreviewTargets(): void
    {
        $hasErrors = false;

        foreach ($this->previewTargets as &$target) {
            $target['label'] = trim((string)$target['label']);
            $target['urlFormat'] = trim((string)$target['urlFormat']);

            if ($target['label'] === '') {
                $target['label'] = ['value' => $target['label'], 'hasErrors' => true];
                $hasErrors = true;
            }
        }
        unset($target);

        if ($hasErrors) {
            $this->errors()->add('previewTargets', t('All targets must have a label.', category: 'app'));
        }
    }

    #[\Deprecated(message: 'in 4.0.0. Use `ProductType::variantTitleFormat` instead.')]
    public function getTitleFormat(): string
    {
        Deprecator::log('craft\commerce\models\ProductType::titleFormat', 'Getting `ProductType::titleFormat` has been deprecated. Use `ProductType::variantTitleFormat` instead.');
        return $this->variantTitleFormat;
    }

    #[\Deprecated(message: 'in 4.0.0. Use `ProductType::variantTitleFormat` instead.')]
    public function setTitleFormat(string $titleFormat): void
    {
        Deprecator::log('craft\commerce\models\ProductType::titleFormat', 'Setting `ProductType::titleFormat` has been deprecated. Use `ProductType::variantTitleFormat` instead.');
        $this->variantTitleFormat = $titleFormat;
    }

    public function extraFields(): array
    {
        return ['taxCategories', 'shippingCategories', 'siteSettings'];
    }

    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'enableVersioning' => $this->enableVersioning,
            'hasDimensions' => $this->hasDimensions,
            'maxVariants' => $this->maxVariants,

            'hasVariantTitleField' => $this->hasVariantTitleField,
            'variantTitleFormat' => $this->variantTitleFormat,
            'variantTitleTranslationMethod' => $this->variantTitleTranslationMethod,
            'variantTitleTranslationKeyFormat' => $this->variantTitleTranslationKeyFormat,
            'variantUiLabelFormat' => $this->variantUiLabelFormat,

            'hasProductTitleField' => $this->hasProductTitleField,
            'productTitleFormat' => $this->productTitleFormat,
            'productTitleTranslationMethod' => $this->productTitleTranslationMethod,
            'productTitleTranslationKeyFormat' => $this->productTitleTranslationKeyFormat,
            'productUiLabelFormat' => $this->productUiLabelFormat,

            'showSlugField' => $this->showSlugField,
            'slugTranslationMethod' => $this->slugTranslationMethod,
            'slugTranslationKeyFormat' => $this->slugTranslationKeyFormat,

            'propagationMethod' => $this->propagationMethod->value,

            'skuFormat' => $this->skuFormat,
            'descriptionFormat' => $this->descriptionFormat,
            'siteSettings' => [],

            'isStructure' => $this->isStructure,
            'maxLevels' => $this->maxLevels,
            'defaultPlacement' => $this->defaultPlacement,
        ];

        if (!empty($this->previewTargets)) {
            $config['previewTargets'] = ProjectConfigHelper::packAssociativeArray(array_values($this->previewTargets));
        }

        if ($this->isStructure) {
            $config['structure'] = [
                'uid' => $this->structureId ? Db::uidById(CraftTable::STRUCTURES, $this->structureId) : (string)Str::uuid(),
            ];
        }

        $generateLayoutConfig = function(FieldLayout $fieldLayout): array {
            $fieldLayoutConfig = $fieldLayout->getConfig();

            if ($fieldLayoutConfig) {
                if (empty($fieldLayout->id)) {
                    $layoutUid = (string)Str::uuid();
                    $fieldLayout->uid = $layoutUid;
                } else {
                    $layoutUid = Db::uidById(CraftTable::FIELDLAYOUTS, $fieldLayout->id);
                }

                return [$layoutUid => $fieldLayoutConfig];
            }

            return [];
        };

        $config['productFieldLayouts'] = $generateLayoutConfig($this->getFieldLayout());
        $config['variantFieldLayouts'] = $generateLayoutConfig($this->getVariantFieldLayout());

        $allSiteSettings = $this->getSiteSettings();

        foreach ($allSiteSettings as $siteId => $settings) {
            $siteUid = Db::uidById(CraftTable::SITES, $siteId);
            $config['siteSettings'][$siteUid] = [
                'hasUrls' => $settings->hasUrls,
                'enabledByDefault' => $settings->enabledByDefault,
                'uriFormat' => $settings->uriFormat,
                'template' => $settings->template,
            ];
        }

        return $config;
    }
}
