<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Elements;

use craft\commerce\elements\VariantCollection;
use craft\commerce\Plugin;
use craft\events\ElementCriteriaEvent;
use CraftCms\Cms\Asset\Actions\CopyReferenceTag;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Cp\Html\ElementHtml;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Element\Actions\Duplicate;
use CraftCms\Cms\Element\Actions\Restore;
use CraftCms\Cms\Element\Actions\SetStatus;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\CurrentElementIndex;
use CraftCms\Cms\Element\Data\EagerLoadPlan;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Enums\PropagationMethod;
use CraftCms\Cms\Element\NestedElementManager;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Entry\Actions\NewChild;
use CraftCms\Cms\Entry\Actions\NewSiblingAfter;
use CraftCms\Cms\Entry\Actions\NewSiblingBefore;
use CraftCms\Cms\Field\Enums\TranslationMethod;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Http\Controllers\NestedElementsController;
use CraftCms\Cms\Http\Requests\ElementRequest;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Structure\Enums\Mode as StructureMode;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\DeltaRegistry;
use CraftCms\Cms\Support\Facades\ElementActions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\InputNamespace;
use CraftCms\Cms\Support\Facades\Revisions;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Structures;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Sequence;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Catalog\Conditions\ProductCondition;
use CraftCms\Commerce\Catalog\Conditions\ProductTypeConditionRule;
use CraftCms\Commerce\Catalog\Jobs\ResaveProductVariantsJob;
use CraftCms\Commerce\Catalog\Models\Product as ProductRecord;
use CraftCms\Commerce\Catalog\Products;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Catalog\Queries\ProductQuery;
use CraftCms\Commerce\Catalog\Queries\VariantQuery;
use CraftCms\Commerce\Catalog\Validation\ProductRules;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Promotion\Actions\CreateDiscount;
use CraftCms\Commerce\Promotion\Actions\CreateSale;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Validation\Validator;
use Override;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;

/**
 * Product element.
 *
 * @property Variant $defaultVariant the default variant
 * @property null|ShippingCategory $shippingCategory the shipping category
 * @property int $totalStock
 * @property Variant $cheapestVariant
 * @property ProductType $type
 * @property VariantCollection $variants the product's variants
 * @property-read string $defaultPriceAsCurrency
 * @property-read string $defaultBasePriceAsCurrency
 * @property-read string $defaultBasePromotionalPriceAsCurrency
 * @property float|null $defaultPrice
 */
#[Ruleset(ProductRules::class)]
class Product extends Element implements HasStoreInterface
{
    use StoreTrait;

    public const string STATUS_LIVE = 'live';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_EXPIRED = 'expired';

    /**
     * @event ElementCriteriaEvent The event that is triggered when defining the parent selection criteria.
     *
     * @see parentOptionCriteria()
     */
    public const string EVENT_DEFINE_PARENT_SELECTION_CRITERIA = 'defineParentSelectionCriteria';

    /**
     * @var DateTime|null Post date
     */
    public ?DateTime $postDate = null;

    /**
     * @var DateTime|null Expiry date
     */
    public ?DateTime $expiryDate = null;

    /**
     * @var int|null Product type ID
     */
    public ?int $typeId = null;

    public ?int $defaultVariantId = null;

    /**
     * @var string|null Default SKU
     */
    public ?string $defaultSku = null;

    /**
     * @see getDefaultPrice()
     * @see setDefaultPrice()
     */
    private ?float $_defaultPrice = null;

    public ?float $defaultBasePrice = null;

    public ?float $defaultBasePromotionalPrice = null;

    public ?float $defaultHeight = null;

    public ?float $defaultLength = null;

    public ?float $defaultWidth = null;

    public ?float $defaultWeight = null;

    public ?TaxCategory $taxCategory = null;

    public ?string $name = null;

    /**
     * @var VariantCollection|null This product’s variants
     */
    private ?VariantCollection $_variants = null;

    /**
     * @see getVariantManager()
     */
    private ?NestedElementManager $_variantManager = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Product', category: 'commerce');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('product', category: 'commerce');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Products', category: 'commerce');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('products', category: 'commerce');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'product';
    }

    #[Override]
    public static function hasDrafts(): bool
    {
        return true;
    }

    #[Override]
    public static function trackChanges(): bool
    {
        return true;
    }

    #[Override]
    public static function hasTitles(): bool
    {
        return true;
    }

    #[Override]
    public static function hasUris(): bool
    {
        return true;
    }

    #[Override]
    public static function isLocalized(): bool
    {
        return true;
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => t('Live', category: 'commerce'),
            self::STATUS_PENDING => t('Pending', category: 'commerce'),
            self::STATUS_EXPIRED => t('Expired', category: 'commerce'),
            self::STATUS_DISABLED => t('Disabled', category: 'commerce'),
        ];
    }

    /**
     * @return ProductQuery The newly created ProductQuery instance.
     */
    #[Override]
    public static function find(): ProductQuery
    {
        return new ProductQuery();
    }

    /**
     * @return ProductCondition
     */
    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new ProductCondition(static::class);
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        // TODO: migrate to app(ProductTypes::class) once service migrated to src/
        $productTypesService = app(ProductTypes::class);

        if ($context == 'index') {
            $productTypes = $productTypesService->getViewableProductTypes();
            $editable = true;
        } else {
            $productTypes = $productTypesService->getAllProductTypes();
            $editable = null;
        }

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            $productTypeIds[] = $productType->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => t('All products', category: 'commerce'),
                'criteria' => [
                    'typeId' => $productTypeIds,
                    'editable' => $editable,
                ],
                'defaultSort' => ['postDate', 'desc'],
            ],
        ];

        $sources[] = ['heading' => t('Product Types', category: 'commerce')];

        $user = currentUser();

        foreach ($productTypes as $productType) {
            $key = 'productType:' . $productType->uid;
            $canSaveProducts = $user && $user->can('commerce-saveProductType:' . $productType->uid);

            $sources[$key] = [
                'key' => $key,
                'label' => t($productType->name, category: 'site'),
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => $canSaveProducts,
                ],
                'criteria' => [
                    'typeId' => $productType->id,
                    'editable' => $editable,
                ],
                // Get site ids enabled for this product type
                'sites' => $productType->getSiteIds(),
            ];

            if ($productType->isStructure) {
                $sources[$key]['defaultSort'] = ['structure', 'asc'];
                $sources[$key]['structureId'] = $productType->structureId;
                $sources[$key]['structureEditable'] = $canSaveProducts;
            } else {
                $sources[$key]['defaultSort'] = ['postDate', 'desc'];
            }
        }

        return $sources;
    }

    #[Override]
    public static function modifyCustomSource(array $config): array
    {
        try {
            /** @var ProductCondition $condition */
            $condition = Conditions::createCondition($config['condition']);
        } catch (\RuntimeException) {
            return $config;
        }

        $rules = $condition->getConditionRules();

        // see if it's limited to one product type
        /** @var ProductTypeConditionRule|null $productTypeRule */
        $productTypeRule = collect($rules)->first(fn($rule) => $rule instanceof ProductTypeConditionRule);
        $productTypeOptions = $productTypeRule?->getValues();

        if ($productTypeOptions && count($productTypeOptions) === 1) {
            // TODO: migrate to app(ProductTypes::class)->getProductTypeByUid() once service migrated to src/
            $productType = app(ProductTypes::class)->getProductTypeByUid(reset($productTypeOptions));
            if ($productType) {
                $config['data']['handle'] = $productType->handle;
            }
        }

        return $config;
    }

    #[Override]
    protected static function defineFieldLayouts(?string $source): array
    {
        // TODO: migrate to app(ProductTypes::class) once service migrated to src/
        $productTypesService = app(ProductTypes::class);

        if ($source === null || $source === '*') {
            $productTypes = $productTypesService->getAllProductTypes();
        } else {
            $productTypes = [];
            if (preg_match('/^productType:(.+)$/', $source, $matches)) {
                $productType = $productTypesService->getProductTypeByUid($matches[1]);
                if ($productType) {
                    $productTypes[] = $productType;
                }
            }
        }

        return array_map(fn(ProductType $productType) => $productType->getFieldLayout(), $productTypes);
    }

    #[Override]
    protected static function defineActions(string $source): array
    {
        // Get the selected site
        $elementQuery = app(CurrentElementIndex::class)->isActive()
            ? app(CurrentElementIndex::class)->query()
            : null;
        $site = $elementQuery && $elementQuery->siteId
            ? Sites::getSiteById($elementQuery->siteId)
            : Sites::getCurrentSite();

        // TODO: migrate to app(ProductTypes::class) once service migrated to src/
        $productTypesService = app(ProductTypes::class);

        // Get the product type(s) we need to check permissions on
        $productTypes = [];

        if ($source === '*') {
            $productTypes = $productTypesService->getViewableProductTypes();
        } elseif (preg_match('/^productType:(\d+)$/', $source, $matches)) {
            $productType = $productTypesService->getProductTypeById((int)$matches[1]);

            if ($productType) {
                $productTypes = [$productType];
            }
        } elseif (preg_match('/^productType:(.+)$/', $source, $matches)) {
            $productType = $productTypesService->getProductTypeByUid($matches[1]);

            if ($productType) {
                $productTypes = [$productType];
            }
        }

        $actions = [];

        // Copy Reference Tag
        $actions[] = ElementActions::createAction([
            'type' => CopyReferenceTag::class,
        ], static::class);

        // Restore
        $actions[] = ElementActions::createAction([
            'type' => Restore::class,
            'successMessage' => t('Products restored.', category: 'commerce'),
            'partialSuccessMessage' => t('Some products restored.', category: 'commerce'),
            'failMessage' => t('Products not restored.', category: 'commerce'),
        ], static::class);

        if ($source === '*') {
            // Delete
            $actions[] = Delete::class;
        } elseif (!empty($productTypes)) {
            $currentUser = currentUser();

            foreach ($productTypes as $productType) {
                $canDelete = $currentUser?->can('commerce-deleteProductType:' . $productType->uid);
                $canCreate = $currentUser?->can('commerce-createProductType:' . $productType->uid);
                $canSave = $currentUser?->can('commerce-saveProductType:' . $productType->uid);

                if ($canCreate && $canSave) {
                    // Duplicate
                    $actions[] = [
                        'type' => Duplicate::class,
                        'asDrafts' => true,
                    ];
                }

                if ($canDelete) {
                    // Allow deletion
                    $actions[] = ElementActions::createAction([
                        'type' => Delete::class,
                        'confirmationMessage' => t('Are you sure you want to delete the selected product and its variants?', category: 'commerce'),
                        'successMessage' => t('Products and Variants deleted.', category: 'commerce'),
                    ], static::class);
                }

                if ($canSave) {
                    $actions[] = SetStatus::class;
                }

                if ($productType->isStructure && $canCreate) {
                    if ($productType->maxLevels != 1) {
                        $actions[] = [
                            'type' => Duplicate::class,
                            'asDrafts' => true,
                            'deep' => true,
                        ];
                    }

                    $newProductUrl = 'commerce/products/' . $productType->handle . '/new';

                    if (Sites::isMultiSite()) {
                        $newProductUrl .= '?site=' . $site->handle;
                    }

                    $actions[] = ElementActions::createAction([
                        'type' => NewSiblingBefore::class,
                        'newSiblingUrl' => $newProductUrl,
                    ], static::class);

                    $actions[] = ElementActions::createAction([
                        'type' => NewSiblingAfter::class,
                        'newSiblingUrl' => $newProductUrl,
                    ], static::class);

                    if ($productType->maxLevels != 1) {
                        $actions[] = ElementActions::createAction([
                            'type' => NewChild::class,
                            'maxLevels' => $productType->maxLevels,
                            'newChildUrl' => $newProductUrl,
                        ], static::class);
                    }
                }
            }

            if ($currentUser?->can('commerce-managePromotions')) {
                // TODO: migrate to app(Sales::class)->canUseSales() once the Sales element actions are migrated to src/
                if (app(Sales::class)->canUseSales()) {
                    $actions[] = CreateSale::class;
                }

                $actions[] = CreateDiscount::class;
            }
        }

        return $actions;
    }

    #[Override]
    protected function safeActionMenuItems(): array
    {
        $actions = parent::safeActionMenuItems();

        if (
            app(ElementRequest::class)->element === $this &&
            currentUser()?->isAdmin() &&
            Cms::config()->allowAdminChanges
        ) {
            // Product type settings
            $productTypeEditId = sprintf('edit-product-type-%s', mt_rand());
            $actions[] = [
                'id' => $productTypeEditId,
                'icon' => 'gear',
                'label' => t('Product type settings', category: 'commerce'),
            ];

            HtmlStack::jsWithVars(fn($id, $params) => <<<JS
(() => {
  $('#' + $id).on('activate', function() {
    const params = $params;
    new Craft.CpScreenSlideout('commerce/product-types/edit-product-type', {params});
  });
})();
JS, [
                InputNamespace::namespaceId($productTypeEditId),
                ['productTypeId' => $this->typeId],
            ]);
        }

        return $actions;
    }

    #[Override]
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        return [
            'title' => t('Title', category: 'commerce'),
            [
                'label' => t('Post Date', category: 'commerce'),
                'orderBy' => 'postDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Expiry Date', category: 'commerce'),
                'orderBy' => 'expiryDate',
                'defaultDir' => 'desc',
            ],
            'promotable' => t('Promotable?', category: 'commerce'),
            'defaultPrice' => t('Price', category: 'commerce'),
            'defaultSku' => t('SKU', category: 'commerce'),
            [
                'label' => t('Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => t('Product', category: 'commerce')],
            'status' => ['label' => t('Status', category: 'commerce')],
            'id' => ['label' => t('ID', category: 'commerce')],
            'type' => ['label' => t('Type', category: 'commerce')],
            'slug' => ['label' => t('Slug', category: 'commerce')],
            'uri' => ['label' => t('URI', category: 'commerce')],
            'postDate' => ['label' => t('Post Date', category: 'commerce')],
            'expiryDate' => ['label' => t('Expiry Date', category: 'commerce')],
            'stock' => ['label' => t('Stock', category: 'commerce')],
            'link' => ['label' => t('Link', category: 'commerce'), 'icon' => 'world'],
            'dateCreated' => ['label' => t('Date Created', category: 'commerce')],
            'dateUpdated' => ['label' => t('Date Updated', category: 'commerce')],
            'defaultPrice' => ['label' => t('Price', category: 'commerce')],
            'defaultPromotionalPrice' => ['label' => t('Promotional Price', category: 'commerce')],
            'defaultSku' => ['label' => t('SKU', category: 'commerce')],
            'defaultWeight' => ['label' => t('Weight', category: 'commerce')],
            'defaultLength' => ['label' => t('Length', category: 'commerce')],
            'defaultWidth' => ['label' => t('Width', category: 'commerce')],
            'defaultHeight' => ['label' => t('Height', category: 'commerce')],
            'variants' => ['label' => t('Variants', category: 'commerce')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source == '*') {
            $attributes[] = 'type';
        }

        $attributes[] = 'status';
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';
        $attributes[] = 'defaultPrice';
        $attributes[] = 'defaultSku';
        $attributes[] = 'link';

        return $attributes;
    }

    #[Override]
    public static function attributePreviewHtml(array $attribute): mixed
    {
        return match ($attribute['value']) {
            'defaultSku' => $attribute['placeholder'],
            default => parent::attributePreviewHtml($attribute)
        };
    }

    #[Override]
    protected static function defineCardAttributes(): array
    {
        return array_merge(parent::defineCardAttributes(), [
            'defaultPrice' => [
                'label' => t('Price', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(123.99),
            ],
            'defaultPromotionalPrice' => [
                'label' => t('Promotional Price', category: 'commerce'),
                'placeholder' => '¤' . I18N::getFormatter()->asDecimal(123.99),
            ],
            'defaultSku' => [
                'label' => t('SKU', category: 'commerce'),
                'placeholder' => Html::tag('code', 'SKU123'),
            ],
        ]);
    }

    #[Override]
    protected static function defineDefaultCardAttributes(): array
    {
        return array_merge(parent::defineDefaultCardAttributes(), [
            'defaultSku',
            'defaultPrice',
        ]);
    }

    #[Override]
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle == 'variants') {
            $sourceElementIds = array_filter(array_map(fn(ElementInterface $element) => $element->id, $sourceElements));

            $map = DB::table(CraftTable::ELEMENTS_OWNERS)
                ->select(['ownerId as source', 'elementId as target'])
                ->whereIn('ownerId', $sourceElementIds)
                ->orderBy('sortOrder')
                ->get()
                ->map(fn(object $row) => (array)$row)
                ->all();

            return [
                'elementType' => Variant::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    public static function gqlTypeNameByContext(mixed $context): string
    {
        /** @var ProductType $context */
        return $context->handle . '_Product';
    }

    #[Override]
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var ProductType $context */
        return ['productTypes.' . $context->uid];
    }

    #[Override]
    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        // Only eager load variants for attributes that actually need them.
        // Other variant-related attributes (defaultPrice, defaultSku, etc.) are already
        // fetched via SQL JOINs in ProductQuery
        if (in_array($attribute, ['variants', 'stock'], true)) {
            $elementQuery->andWith('variants');
        } else {
            parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }

    /**
     * The attributes on the product that should be made available as formatted currency.
     */
    public function currencyAttributes(): array
    {
        return ['defaultPrice', 'defaultBasePrice', 'defaultBasePromotionalPrice'];
    }

    public function getDefaultPriceAsCurrency(): string
    {
        return $this->currencyAttributeAsCurrency('defaultPrice');
    }

    public function getDefaultBasePriceAsCurrency(): string
    {
        return $this->currencyAttributeAsCurrency('defaultBasePrice');
    }

    public function getDefaultBasePromotionalPriceAsCurrency(): string
    {
        return $this->currencyAttributeAsCurrency('defaultBasePromotionalPrice');
    }

    /**
     * The legacy element used the `CurrencyAttributeBehavior` Yii behavior to generate
     * `<attribute>AsCurrency` magic getters; the new base has no behaviors, so those getters are
     * declared explicitly (same approach as `Order` and `Purchasable`).
     */
    private function currencyAttributeAsCurrency(string $attribute): string
    {
        $amount = $this->$attribute ?? 0;
        return \CraftCms\Commerce\Helpers\Currency::formatAsCurrency($amount, $this->getStore()->getCurrency());
    }

    #[Override]
    public function fields(): array
    {
        $fields = parent::fields();

        foreach ($this->currencyAttributes() as $attribute) {
            $fields[$attribute . 'AsCurrency'] = $attribute . 'AsCurrency';
        }

        return $fields;
    }

    public function setDefaultPrice(?float $defaultPrice): void
    {
        $this->_defaultPrice = $defaultPrice;
    }

    public function getDefaultPrice(): ?float
    {
        return $this->_defaultPrice ?? $this->getDefaultVariant()?->price;
    }

    #[Override]
    public function canCreateDrafts(\CraftCms\Cms\User\Elements\User $user): bool
    {
        // Everyone with view permissions can create drafts
        return true;
    }

    #[Override]
    public function hasRevisions(): bool
    {
        return $this->getType()->enableVersioning;
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('commerce/products');
    }

    #[Override]
    protected function cpRevisionsUrl(): ?string
    {
        return sprintf('%s/revisions', $this->cpEditUrl());
    }

    #[Override]
    public function getIsTitleTranslatable(): bool
    {
        return $this->getType()->productTitleTranslationMethod !== TranslationMethod::None->value;
    }

    #[Override]
    public function getTitleTranslationDescription(): ?string
    {
        /** @phpstan-ignore-next-line nullsafe.neverNull (productTitleTranslationMethod is an uncast, free-form DB string column - tryFrom() genuinely can return null) */
        return TranslationMethod::tryFrom($this->getType()->productTitleTranslationMethod)?->description();
    }

    #[Override]
    public function getTitleTranslationKey(): string
    {
        $type = $this->getType();

        // productTitleTranslationMethod is an uncast, free-form DB string column - tryFrom() genuinely can return null
        /** @phpstan-ignore-next-line nullCoalesce.expr */
        return TranslationMethod::tryFrom($type->productTitleTranslationMethod)
            ?->elementKey($this, $type->productTitleTranslationKeyFormat) /** @phpstan-ignore-line */
            ?? (string)$this->siteId;
    }

    #[Override]
    public function getIsSlugTranslatable(): bool
    {
        return $this->getType()->slugTranslationMethod !== TranslationMethod::None->value;
    }

    #[Override]
    public function getSlugTranslationDescription(): ?string
    {
        /** @phpstan-ignore-next-line nullsafe.neverNull (slugTranslationMethod is an uncast, free-form DB string column - tryFrom() genuinely can return null) */
        return TranslationMethod::tryFrom($this->getType()->slugTranslationMethod)?->description();
    }

    #[Override]
    public function getSlugTranslationKey(): string
    {
        $type = $this->getType();

        // slugTranslationMethod is an uncast, free-form DB string column - tryFrom() genuinely can return null
        /** @phpstan-ignore-next-line nullCoalesce.expr */
        return TranslationMethod::tryFrom($type->slugTranslationMethod)
            ?->elementKey($this, $type->slugTranslationKeyFormat) /** @phpstan-ignore-line */
            ?? (string)$this->siteId;
    }

    #[Override]
    public function __toString(): string
    {
        return (string)$this->title;
    }

    #[Override]
    public function canView(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('commerce-viewProductType:' . $productType->uid);
    }

    #[Override]
    public function canSave(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        if ($this->getIsDraft()) {
            return $this->canCreateDrafts($user);
        }

        // New products require create permission
        if (!$this->id) {
            return $user->can('commerce-createProductType:' . $productType->uid);
        }

        return $user->can('commerce-saveProductType:' . $productType->uid);
    }

    #[Override]
    public function canDuplicate(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('commerce-createProductType:' . $productType->uid)
            && $user->can('commerce-saveProductType:' . $productType->uid);
    }

    #[Override]
    public function canDelete(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('commerce-deleteProductType:' . $productType->uid);
    }

    /**
     * Products can be deleted for a single site by anyone who can delete the product. The legacy
     * element deferred to `Elements::canDelete()`; the new Elements service has no such method
     * (authorization is policy-based now), so the element's own check is used directly.
     */
    #[Override]
    public function canDeleteForSite(\CraftCms\Cms\User\Elements\User $user): bool
    {
        return $this->canDelete($user);
    }

    #[Override]
    public function createAnother(): ?ElementInterface
    {
        return null;
    }

    #[Override]
    protected function crumbs(): array
    {
        $productType = $this->getType();

        // TODO: migrate to app(ProductTypes::class)->getViewableProductTypes() once service migrated to src/
        $productTypes = Collection::make(app(ProductTypes::class)->getViewableProductTypes());

        $productTypeOptions = $productTypes
            ->map(fn(ProductType $t) => [
                'label' => t($t->name, category: 'site'),
                'url' => "commerce/products/$t->handle",
                'selected' => $t->id === $productType->id,
            ]);

        return [
            [
                'label' => t('Products', category: 'commerce'),
                'url' => 'commerce/products',
            ],
            [
                'menu' => [
                    'label' => t('Select product type', category: 'commerce'),
                    'items' => $productTypeOptions->all(),
                ],
            ],
        ];
    }

    #[Override]
    protected function uiLabel(): ?string
    {
        // This method is called in a few places before the product type is set
        // If there isn't a type then fall back to the title
        if ($this->typeId) {
            $uiLabelFormat = $this->getType()->productUiLabelFormat;
            if ($uiLabelFormat !== '{title}') {
                $uiLabel = renderSandboxedObjectTemplate($uiLabelFormat, $this);
                if ($uiLabel !== '') {
                    return $uiLabel;
                }
            }
        }

        if (!isset($this->title) || trim($this->title) === '') {
            return t('Untitled {type}', [
                'type' => self::lowerDisplayName(),
            ]);
        }

        return null;
    }

    /**
     * Returns the product's product type.
     *
     * @throws \RuntimeException
     */
    public function getType(): ProductType
    {
        if ($this->typeId === null) {
            throw new \RuntimeException('Product is missing its product type ID');
        }

        // TODO: migrate to app(ProductTypes::class)->getProductTypeById() once service migrated to src/
        $productType = app(ProductTypes::class)->getProductTypeById($this->typeId);

        if ($productType === null) {
            throw new \RuntimeException('Invalid product type ID: ' . $this->typeId);
        }

        return $productType;
    }

    public function getName(): ?string
    {
        return $this->title;
    }

    #[Override]
    protected function cacheTags(): array
    {
        return [
            "productType:$this->typeId",
        ];
    }

    #[Override]
    public function getUriFormat(): ?string
    {
        $productTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($productTypeSiteSettings[$this->siteId])) {
            throw new \RuntimeException('The "' . $this->getType()->name . '" product type is not enabled for the "' . $this->getSite()->name . '" site.');
        }

        return $productTypeSiteSettings[$this->siteId]->uriFormat;
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        $productType = $this->getType();

        $path = sprintf('commerce/products/%s/%s', $productType->handle, $this->getCanonicalId());

        // Ignore homepage/temp slugs
        if ($this->slug && !str_starts_with($this->slug, '__')) {
            $path .= sprintf('-%s', str_replace('/', '-', $this->slug));
        }

        return $path;
    }

    /**
     * Returns the default variant.
     *
     * @throws \RuntimeException
     */
    public function getDefaultVariant(bool $includeDisabled = false): ?Variant
    {
        $defaultVariant = $this->getVariants($includeDisabled)->firstWhere('id', $this->defaultVariantId);

        return $defaultVariant ?: $this->getVariants($includeDisabled)->first();
    }

    /**
     * Return the cheapest variant.
     *
     * @throws \RuntimeException
     */
    public function getCheapestVariant(bool $includeDisabled = false): ?Variant
    {
        return $this->getVariants($includeDisabled)->cheapest();
    }

    /**
     * Returns a collection of the product's variants.
     *
     * @throws \RuntimeException
     */
    public function getVariants(?bool $includeDisabled = null): VariantCollection
    {
        if ($this->_variants === null) {
            if (!$this->id) {
                return VariantCollection::make();
            }

            /** @var self|null $duplicatingProduct */
            $duplicatingProduct = $this->duplicateOf;
            if ($duplicatingProduct) {
                $query = self::createVariantQuery($duplicatingProduct)->status(null);
            } else {
                $query = self::createVariantQuery($this)->status(null);
            }

            $variants = $query->collect();

            // Don't memoize empty collections in favour of a new query next time
            if ($variants->isEmpty()) {
                return $variants;
            }

            $this->_variants = $variants;
            $this->_variants->map(function(Variant $v) {
                if (!$this->id) {
                    return $v;
                }

                if ($v->primaryOwnerId === $this->id) {
                    $v->setPrimaryOwner($this);
                }

                if ($v->ownerId === $this->id) {
                    $v->setOwner($this);
                }

                return $v;
            });
        }

        // When reordering variants we need to make sure disabled variants are included when calculating sort order
        // @TODO Remove this controller-based default in Commerce 6.0 when `getVariants()` is updated to return an element query instance
        $includeDisabled ??= ltrim((string)request()->route()?->getControllerClass(), '\\') === NestedElementsController::class;

        return $this->_variants->filter(fn(Variant $variant) => $includeDisabled || ($variant->getStatus() === self::STATUS_ENABLED));
    }

    #[Override]
    public function getSupportedSites(): array
    {
        if (!isset($this->typeId)) {
            throw new \RuntimeException('Require `typeId` must be set on the product.');
        }

        $productType = $this->getType();
        /** @var Collection<int, Site> $allSites */
        $allSites = Sites::getAllSites(true)->keyBy('id');
        $sites = [];
        $currentSites = [];

        // If the product type is leaving it up to products to decide which sites to be propagated to,
        // figure out which sites the product is currently saved in
        if (
            ($this->duplicateOf->id ?? $this->id) &&
            $productType->propagationMethod === PropagationMethod::Custom
        ) {
            if ($this->id) {
                $currentSites = self::find()
                    ->status(null)
                    ->id($this->id)
                    ->site('*')
                    ->drafts(null)
                    ->provisionalDrafts(null)
                    ->revisions($this->getIsRevision())
                    ->pluck('siteId')
                    ->all();
            }

            // If this is being duplicated from another element (e.g. a draft), include any sites the source element is saved to as well
            if (!empty($this->duplicateOf->id)) {
                array_push($currentSites, ...self::find()
                    ->status(null)
                    ->id($this->duplicateOf->id)
                    ->site('*')
                    ->drafts(null)
                    ->provisionalDrafts(null)
                    ->revisions($this->duplicateOf->getIsRevision())
                    ->pluck('siteId')
                    ->all()
                );
            }

            $currentSites = array_flip($currentSites);
        }

        foreach ($productType->getSiteSettings() as $siteSettings) {
            switch ($productType->propagationMethod) {
                case PropagationMethod::None:
                    $include = $siteSettings->siteId == $this->siteId;
                    $propagate = true;
                    break;
                case PropagationMethod::SiteGroup:
                    $include = $allSites[$siteSettings->siteId]->groupId == $allSites[$this->siteId]->groupId;
                    $propagate = true;
                    break;
                case PropagationMethod::Language:
                    $include = $allSites[$siteSettings->siteId]->language == $allSites[$this->siteId]->language;
                    $propagate = true;
                    break;
                case PropagationMethod::Custom:
                    $include = true;
                    // Only actually propagate to this site if it's the current site, or the product has been assigned
                    // a status for this site, or the product already exists for this site
                    $propagate = (
                        $siteSettings->siteId == $this->siteId ||
                        $this->getEnabledForSite($siteSettings->siteId) !== null ||
                        isset($currentSites[$siteSettings->siteId])
                    );
                    break;
                default:
                    $include = $propagate = true;
                    break;
            }

            if ($include) {
                $sites[] = [
                    'siteId' => $siteSettings->siteId,
                    'propagate' => $propagate,
                    'enabledByDefault' => $siteSettings->enabledByDefault,
                ];
            }
        }

        return $sites;
    }

    /**
     * Sets the variants on the product. Accepts an array of variant data keyed by variant ID or the string 'new'.
     *
     * @param VariantCollection|VariantQuery|array<array-key, mixed> $variants
     */
    public function setVariants(VariantCollection|VariantQuery|array $variants): void
    {
        if ($variants instanceof VariantQuery) {
            // just unset our existing records
            $this->_variants = null;
            return;
        }

        // Make sure each variant has an owner set in case of mass assignment of product and variants
        if (is_array($variants)) {
            foreach ($variants as &$variant) {
                if ($variant instanceof Variant) {
                    continue;
                }

                if (is_array($variant) && !isset($variant['owner'])) {
                    $variant = ['owner' => $this] + $variant;
                }
            }
        }

        $this->_variants = $variants instanceof VariantCollection ? $variants : VariantCollection::make($variants);
    }

    /**
     * Returns a nested element manager for the product’s variants.
     */
    public function getVariantManager(): NestedElementManager
    {
        if (!isset($this->_variantManager)) {
            $this->_variantManager = new NestedElementManager(
                Variant::class,
                // @phpstan-ignore argument.type (will always be a Product)
                fn(ElementInterface $product): VariantQuery => self::createVariantQuery($product),
                [
                    'attribute' => 'variants', // dont change this: https://github.com/craftcms/commerce/issues/4314#issuecomment-4715539955
                    'propagationMethod' => $this->getType()->propagationMethod,
                    'valueGetter' => fn() => $this->getVariants(true),
                    'valueSetter' => $this->setVariants(...),
                ],
            );
        }

        return $this->_variantManager;
    }

    #[Override]
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status == self::STATUS_ENABLED && $this->postDate) {
            $currentTime = time();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate?->getTimestamp();

            if ($postDate <= $currentTime && ($expiryDate === null || $expiryDate > $currentTime)) {
                return self::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * @throws \RuntimeException
     */
    public function getTotalStock(bool $includeDisabled = false): int
    {
        $stock = 0;
        foreach ($this->getVariants($includeDisabled) as $variant) {
            $stock += $variant->getStock();
        }

        return $stock;
    }

    #[Override]
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this->getType());
    }

    #[Override]
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle == 'variants') {
            /** @var Variant[] $elements */
            $this->setVariants($elements);
        } else {
            parent::setEagerLoadedElements($handle, $elements, $plan);
        }
    }

    #[Override]
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [];
        $productType = $this->getType();

        // Slug
        if ($productType->showSlugField) {
            $fields[] = $this->slugFieldHtml($static);
        }

        if ($productType->isStructure && $productType->maxLevels !== 1) {
            $fields[] = (function() use ($static, $productType) {
                if ($parentId = $this->getParentId()) {
                    // TODO: migrate to app(Products::class)->getProductById() signature once it accepts criteria
                    $parent = app(Products::class)->getProductById($parentId, $this->siteId, [
                        'drafts' => null,
                        'draftOf' => false,
                    ]);
                } else {
                    // If the product already has structure data, use it. Otherwise, use its canonical product
                    /** @var self|null $parent */
                    $parent = self::find()
                        ->siteId($this->siteId)
                        ->ancestorOf($this->lft ? $this : ($this->getIsCanonical() ? $this->id : $this->getCanonical(true)))
                        ->ancestorDist(1)
                        ->drafts(null)
                        ->draftOf(false)
                        ->status(null)
                        ->one();
                }

                return FormFields::elementSelectFieldHtml([
                    'label' => t('Parent'),
                    'id' => 'parentId',
                    'name' => 'parentId',
                    'elementType' => self::class,
                    'selectionLabel' => t('Choose'),
                    'sources' => ["productType:$productType->uid"],
                    'criteria' => $this->parentOptionCriteria($productType),
                    'limit' => 1,
                    'elements' => $parent ? [$parent] : [],
                    'disabled' => $static,
                    'describedBy' => 'parentId-label',
                    'errors' => $this->errors()->get('parentId'),
                ]);
            })();
        }

        DeltaRegistry::withActive(true, function() {
            DeltaRegistry::registerName('postDate');
            DeltaRegistry::registerName('expiryDate');
        });

        // Post Date
        $fields[] = FormFields::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('postDate'),
            'label' => t('Post Date'),
            'id' => 'postDate',
            'name' => 'postDate',
            'value' => $this->userPostDate(),
            'errors' => $this->errors()->get('postDate'),
            'disabled' => $static,
        ]);

        // Expiry Date
        $fields[] = FormFields::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('expiryDate'),
            'label' => t('Expiry Date'),
            'id' => 'expiryDate',
            'name' => 'expiryDate',
            'value' => $this->expiryDate,
            'errors' => $this->errors()->get('expiryDate'),
            'disabled' => $static,
        ]);

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    /** @return array<string, mixed> */
    private function parentOptionCriteria(ProductType $productType): array
    {
        $parentOptionCriteria = [
            'siteId' => $this->siteId,
            'typeId' => $productType->id,
            'status' => null,
            'drafts' => null,
            'draftOf' => false,
        ];

        // Prevent the current product, or any of its descendants, from being selected as a parent
        if ($this->id) {
            $excludeIds = self::find()
                ->descendantOf($this)
                ->drafts(null)
                ->draftOf(false)
                ->status(null)
                ->ids();
            $excludeIds[] = $this->getCanonicalId();
            $parentOptionCriteria['id'] = array_merge(['not'], $excludeIds);
        }

        if ($productType->maxLevels) {
            if ($this->id) {
                // Figure out how deep the ancestors go
                $maxDepth = self::find()
                    ->select('level')
                    ->descendantOf($this)
                    ->status(null)
                    ->leaves()
                    ->value('level');
                $depth = 1 + ($maxDepth ?: $this->level) - $this->level;
            } else {
                $depth = 1;
            }

            $parentOptionCriteria['level'] = sprintf('<=%s', $productType->maxLevels - $depth);
        }

        // Fire a 'defineParentSelectionCriteria' event
        if ($this->hasEventHandlers(self::EVENT_DEFINE_PARENT_SELECTION_CRITERIA)) {
            $event = new ElementCriteriaEvent(['criteria' => $parentOptionCriteria]);
            $this->trigger(self::EVENT_DEFINE_PARENT_SELECTION_CRITERIA, $event);
            return $event->criteria;
        }

        return $parentOptionCriteria;
    }

    /**
     * Returns the Post Date value that should be shown on the edit form.
     */
    private function userPostDate(): ?DateTime
    {
        if (!$this->postDate || ($this->getIsUnpublishedDraft() && $this->postDate == $this->dateCreated)) {
            // Pretend the post date hasn't been set yet, even if it has
            return null;
        }

        return $this->postDate;
    }

    #[Override]
    public function getMetadata(): array
    {
        $metadata = parent::getMetadata();

        if (array_key_exists(t('Status'), $metadata)) {
            unset($metadata[t('Status')]);
        }

        return $metadata;
    }

    #[Override]
    protected function searchKeywords(string $attribute): string
    {
        if ($attribute === 'sku') {
            return $this->getVariants()
                ->pluck('sku')
                ->filter(fn(?string $sku) => $sku && !PurchasableHelper::isTempSku($sku))
                ->implode(' ');
        }

        return parent::searchKeywords($attribute);
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $productType = $this->getType();

            if (!$isNew) {
                $record = ProductRecord::query()->find($this->id);

                if (!$record) {
                    throw new \Exception('Invalid product ID: ' . $this->id);
                }
            } else {
                $record = new ProductRecord();
                $record->id = $this->id;
            }

            $record->postDate = Query::prepareDateForDb($this->postDate);
            $record->expiryDate = Query::prepareDateForDb($this->expiryDate);
            $record->typeId = $this->typeId;

            $defaultVariant = $this->getDefaultVariant();
            $record->defaultVariantId = $defaultVariant->id ?? null;
            $record->defaultSku = $defaultVariant?->getSkuAsText() ?? '';
            $record->defaultPrice = $defaultVariant?->getBasePrice() ?? 0.0;
            $record->defaultHeight = $defaultVariant->height ?? 0.0;
            $record->defaultLength = $defaultVariant->length ?? 0.0;
            $record->defaultWidth = $defaultVariant->width ?? 0.0;
            $record->defaultWeight = $defaultVariant->weight ?? 0.0;

            // Make sure to update the object
            $this->defaultVariantId = $defaultVariant->id ?? null;
            $this->defaultSku = $defaultVariant?->getSkuAsText();
            $this->defaultPrice = $defaultVariant?->getBasePrice() ?? 0.0;
            $this->defaultHeight = $defaultVariant->height ?? 0;
            $this->defaultLength = $defaultVariant->length ?? 0;
            $this->defaultWidth = $defaultVariant->width ?? 0;
            $this->defaultWeight = $defaultVariant->weight ?? 0;

            // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
            $record->dateUpdated = Query::prepareDateForDb($this->dateUpdated);
            $record->dateCreated = Query::prepareDateForDb($this->dateCreated);

            // Capture the dirty attributes from the record
            $dirtyAttributes = array_keys($record->getDirty());
            $record->save();

            $this->id = $record->id;

            $this->setDirtyAttributes($dirtyAttributes);

            if ($this->getIsCanonical() &&
                isset($this->typeId) &&
                $productType->isStructure
            ) {
                // Has the parent changed?
                if ($this->hasNewParent()) {
                    $this->placeInStructure($isNew, $productType);
                }

                // Update the product's descendants, who may be using this product's URI in their own URIs
                if (!$isNew) {
                    Elements::updateDescendantSlugsAndUris($this, true, true);
                }
            }

            // Queue job to resave variants if the variant title format references the product
            if ($this->getIsCanonical() &&
                isset($this->typeId) &&
                !$productType->hasVariantTitleField &&
                $productType->variantTitleFormat &&
                str($productType->variantTitleFormat)->contains(['product.', 'owner.', 'primaryOwner.'])
            ) {
                ResaveProductVariantsJob::dispatch(productId: $this->id);
            }
        }

        parent::afterSave($isNew);
    }

    private function placeInStructure(bool $isNew, ProductType $productType): void
    {
        $parentId = $this->getParentId();

        // If this is a provisional draft and its new parent matches the canonical product’s, just drop it from the structure
        if ($this->isProvisionalDraft) {
            $canonicalParentId = self::find()
                ->select(['elements.id'])
                ->ancestorOf($this->getCanonicalId())
                ->ancestorDist(1)
                ->status(null)
                ->value('id');

            if ($parentId == $canonicalParentId) {
                Structures::remove($this->structureId, $this);
                return;
            }
        }

        $mode = $isNew ? StructureMode::Insert : StructureMode::Auto;

        if (!$parentId) {
            if ($productType->defaultPlacement === ProductType::DEFAULT_PLACEMENT_BEGINNING) {
                Structures::prependToRoot($this->structureId, $this, $mode);
            } else {
                Structures::appendToRoot($this->structureId, $this, $mode);
            }
        } else {
            if ($productType->defaultPlacement === ProductType::DEFAULT_PLACEMENT_BEGINNING) {
                Structures::prepend($this->structureId, $this, $this->getParent(), $mode);
            } else {
                Structures::append($this->structureId, $this, $this->getParent(), $mode);
            }
        }
    }

    /**
     * Updates the product's title, if its product type has a dynamic title format.
     */
    public function updateTitle(): void
    {
        $productType = $this->getType();

        if (!$productType->hasProductTitleField) {
            // Set Craft to the product's site's language, in case the title format has any static translations
            $language = $this->getSite()->getLanguage();
            $title = I18N::withLocale(
                $language,
                $language,
                fn() => renderSandboxedObjectTemplate($productType->productTitleFormat, $this),
            );

            if ($title !== '') {
                $this->title = $title;
            }
        }
    }

    /**
     * The new validation system has no `beforeValidate(): bool` hook — the equivalent
     * pre-validation mutation point is `prepareForValidation()` (Illuminate-style, runs before
     * rules are applied, no return value).
     */
    #[Override]
    public function prepareForValidation(): void
    {
        // We need to generate all variant sku formats before validating the product,
        // since the product validates the uniqueness of all variants in memory.
        $type = $this->getType();

        foreach ($this->getVariants(true) as $variant) {
            if ($variant->sku || !$type->skuFormat) {
                continue;
            }

            try {
                $variant->sku = renderSandboxedObjectTemplate($type->skuFormat, $variant);
            } catch (\Exception $e) {
                Log::error('Craft Commerce could not generate the supplied SKU format: ' . $e->getMessage());
                $variant->sku = '';
            }

            if (!$variant->sku) {
                continue;
            }

            // Ensure there isn't a clash with an existing SKU when using auto formats
            if ($this->skuExists($variant->sku, $variant->id)) {
                // If there is a clash, we need to append a number to the end.
                $baseSku = $variant->sku;
                do {
                    $seq = Sequence::next('sku::' . $baseSku);
                    $newSku = $baseSku . '-' . $seq;
                } while ($this->skuExists($newSku, $variant->id));

                $variant->sku = $newSku;
            }
        }
    }

    private function skuExists(string $sku, ?int $id): bool
    {
        return DB::table(Table::PURCHASABLES)
            ->where('sku', $sku)
            // Make sure it isn't for the purchasable we are currently saving
            ->when($id, fn($query) => $query->where('id', '!=', $id))
            ->exists();
    }

    /**
     * Runs the imperative validators that used to be wired up via `defineRules()`'s
     * `[[attributes], callable]` syntax. {@see ProductRules} keeps only the plain declarative
     * rules; the variant checks below add errors to the `variants` attribute rather than validating
     * a value of their own, so they live here. This is invoked automatically by
     * {@see \CraftCms\Cms\Validation\Ruleset::after()}.
     */
    #[Override]
    public function afterValidate(?Validator $validator = null): void
    {
        if ($this->ruleset->inScenarios(ProductRules::SCENARIO_LIVE)) {
            $this->validateHasVariants();
            $this->validateVariantSkusAreUnique();
            $this->validateVariantSkusAreSet();
        }

        $this->validateMaxVariants();
    }

    public function validateHasVariants(): void
    {
        if ($this->getVariants(true)->isEmpty()) {
            $this->errors()->add('variants', t('Must have at least one variant.', category: 'commerce'));
        }
    }

    public function validateVariantSkusAreUnique(): void
    {
        $skus = [];

        foreach ($this->getVariants(true) as $variant) {
            if (isset($skus[$variant->sku])) {
                $this->errors()->add('variants', t('Not all SKUs are unique.', category: 'commerce'));
                break;
            }

            $skus[$variant->sku] = true;
        }
    }

    public function validateVariantSkusAreSet(): void
    {
        foreach ($this->getVariants(true) as $variant) {
            if (!$variant->sku || PurchasableHelper::isTempSku($variant->sku)) {
                $this->errors()->add('variants', t('All variants must have a SKU.', category: 'commerce'));
                break;
            }
        }
    }

    public function validateMaxVariants(): void
    {
        $maxVariants = $this->getType()->maxVariants;

        if ($maxVariants && count($this->getVariants(true)) > $maxVariants) {
            $this->errors()->add('variants', t('Too many variants for this product.', category: 'commerce'));
        }
    }

    #[Override]
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        $this->getVariantManager()->deleteNestedElements($this, $this->hardDelete);

        return true;
    }

    #[Override]
    public function afterRestore(): void
    {
        $this->getVariantManager()->restoreNestedElements($this);

        parent::afterRestore();
    }

    #[Override]
    public function setAttributesFromRequest(array $values): void
    {
        // this is needed for Craft.NestedElementManager::markAsDirty()
        if (isset($values['variants']) && $values['variants'] === '*') {
            $this->setDirtyAttributes(['variants']);
            unset($values['variants']);
        }

        parent::setAttributesFromRequest($values);
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        try {
            return $this->getType()->getProductFieldLayout();
        } catch (\RuntimeException) {
            // The product type was probably deleted
            return null;
        }
    }

    #[Override]
    public function beforeSave(bool $isNew): bool
    {
        // Make sure the product has at least one revision if the product type has versioning enabled
        if ($this->shouldSaveRevision()) {
            $hasRevisions = self::find()
                ->revisionOf($this)
                ->site('*')
                ->status(null)
                ->exists();
            if (!$hasRevisions) {
                /** @var self|null $currentProduct */
                $currentProduct = self::find()
                    ->id($this->id)
                    ->site('*')
                    ->status(null)
                    ->one();

                // May be null if the product is currently stored as an unpublished draft
                if ($currentProduct) {
                    $revisionNotes = 'Revision from ' . I18N::getFormatter()->asDatetime($currentProduct->dateUpdated);
                    app(Revisions::class)->createRevision($currentProduct, notes: $revisionNotes);
                }
            }
        }

        $productType = $this->getType();
        // Set the structure ID for Element::attributes() and afterSave()
        if ($productType->isStructure) {
            $this->structureId = $productType->structureId;

            // Has the product been assigned to a new parent?
            if (!$this->duplicateOf && $this->hasNewParent()) {
                if ($parentId = $this->getParentId()) {
                    $parentProduct = app(Products::class)->getProductById($parentId, '*', [
                        'preferSites' => [$this->siteId],
                        'drafts' => null,
                        'draftOf' => false,
                    ]);

                    if (!$parentProduct) {
                        throw new \RuntimeException("Invalid parent ID: $parentId");
                    }
                } else {
                    $parentProduct = null;
                }

                $this->setParent($parentProduct);
            }
        }

        // Make sure the field layout is set correctly
        $this->fieldLayoutId = $this->getType()->fieldLayoutId;

        if ($this->enabled && !$this->postDate) {
            // Default the post date to the current date/time
            $this->postDate = new DateTime();
            // ...without the seconds
            $this->postDate->setTimestamp($this->postDate->getTimestamp() - ($this->postDate->getTimestamp() % 60));
        }

        $this->updateTitle();

        return parent::beforeSave($isNew);
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return [
            'defaultSku',
            'sku',
        ];
    }

    private static function createVariantQuery(Product $product): VariantQuery
    {
        $query = Variant::find()
            ->productId($product->id)
            ->siteId($product->siteId)
            ->orderBy('sortOrder');

        if ($product->getIsRevision()) {
            $query->revisions(null)->trashed(null);
        }

        return $query;
    }

    #[Override]
    protected function route(): array|string|null
    {
        // Make sure that the product is actually live
        if (!$this->previewing && $this->getStatus() != self::STATUS_LIVE) {
            return null;
        }

        // Make sure the product type is set to have URLs for this site
        $siteId = Sites::getCurrentSite()->id;
        $productTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($productTypeSiteSettings[$siteId]) || !$productTypeSiteSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $productTypeSiteSettings[$siteId]->template,
                'variables' => [
                    'product' => $this,
                ],
            ],
        ];
    }

    #[Override]
    protected function previewTargets(): array
    {
        return array_map(function($previewTarget) {
            $previewTarget['label'] = t($previewTarget['label'], category: 'site');
            return $previewTarget;
        }, $this->getType()->previewTargets ?? []);
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        $productType = $this->getType();

        switch ($attribute) {
            case 'type':
            {
                return t(Html::encode($productType->name), category: 'site');
            }
            case 'defaultSku':
            {
                if ($this->defaultSku === null) {
                    return '';
                }

                return Html::tag('code', PurchasableHelper::isTempSku($this->defaultSku) ? '' : Html::encode($this->defaultSku));
            }
            case 'defaultPrice':
            {
                return $this->defaultBasePrice ? $this->getDefaultBasePriceAsCurrency() : '';
            }
            case 'defaultPromotionalPrice':
            {
                return $this->defaultBasePromotionalPrice ? $this->getDefaultBasePromotionalPriceAsCurrency() : '';
            }
            case 'stock':
            {
                $stock = 0;
                $hasUnlimited = false;

                foreach ($this->getVariants(true) as $variant) {
                    $stock += $variant->getStock();
                    if (!$variant->inventoryTracked) {
                        $hasUnlimited = true;
                    }
                }
                return $hasUnlimited ? '∞' . ($stock ? ' & ' . $stock : '') : (string)($stock ?: '0');
            }
            case 'defaultWeight':
            {
                if ($productType->hasDimensions) {
                    return I18N::getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->weightUnits;
                }

                return '';
            }
            case 'defaultLength':
            case 'defaultWidth':
            case 'defaultHeight':
            {
                if ($productType->hasDimensions) {
                    return I18N::getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits;
                }

                return '';
            }
            case 'variants':
            {
                $value = $this->getVariants(true);
                /** @var Variant|null $first */
                $first = $value->first();
                $html = $first ? app(ElementHtml::class)->elementChipHtml($first) : '';

                if ($value->isNotEmpty() && $value->count() > 1) {
                    $otherItems = $value->filter(fn($v, $k) => $k > 0);
                    $otherHtml = $otherItems->map(fn($v) => app(ElementHtml::class)->elementChipHtml($v))->join('');

                    $html .= Html::tag('span', '+' . I18N::getFormatter()->asInteger($otherItems->count()), [
                        'title' => $otherItems->map(fn($v) => $v->title)->join(', '),
                        'class' => 'btn small',
                        'role' => 'button',
                        'onclick' => 'jQuery(this).replaceWith(' . Json::encode($otherHtml) . ')',
                    ]);
                }

                return $html;
            }
            default:
            {
                return parent::attributeHtml($attribute);
            }
        }
    }

    #[Override]
    public function afterPropagate(bool $isNew): void
    {
        $this->getVariantManager()->maintainNestedElements($this, $isNew);
        parent::afterPropagate($isNew);

        // @TODO Collate purchasable IDs updated across the request and queue a single catalog pricing job, rather than one per product propagate
        if (!$this->getIsDraft()) {
            app(CatalogPricing::class)->createCatalogPricingJob([
                'purchasableIds' => $this->getVariants()->pluck('id')->all(),
                'storeId' => $this->storeId,
            ]);
        }

        // Save a new revision?
        if ($this->shouldSaveRevision()) {
            app(Revisions::class)->createRevision($this, notes: $this->revisionNotes);
        }
    }

    /**
     * Returns whether the product should be saving revisions on save.
     */
    private function shouldSaveRevision(): bool
    {
        return (
            $this->id &&
            !$this->propagating &&
            !$this->resaving &&
            !$this->getIsDraft() &&
            !$this->getIsRevision() &&
            $this->getType()->enableVersioning
        );
    }
}
