<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\elements\actions\CreateDiscount;
use craft\commerce\elements\actions\CreateSale;
use craft\commerce\elements\conditions\products\ProductCondition;
use craft\commerce\elements\conditions\products\ProductTypeConditionRule;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\ProductType;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\commerce\records\Product as ProductRecord;
use craft\db\Query;
use craft\elements\actions\CopyReferenceTag;
use craft\elements\actions\Delete;
use craft\elements\actions\Duplicate;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\EagerLoadPlan;
use craft\elements\db\ElementQueryInterface;
use craft\elements\NestedElementManager;
use craft\elements\User;
use craft\enums\PropagationMethod;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\Site;
use craft\validators\DateTimeValidator;
use DateTime;
use Illuminate\Support\Collection;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Product model.
 *
 * @property Variant $defaultVariant the default variant
 * @property string $eagerLoadedElements some eager-loaded elements on a given handle
 * @property string $editorHtml the HTML for the element’s editor HUD
 * @property null|ShippingCategory $shippingCategory the shipping category
 * @property string $snapshot allow the variant to ask the product what data to snapshot
 * @property int $totalStock
 * @property bool $hasUnlimitedStock whether at least one variant has unlimited stock
 * @property Variant $cheapestVariant
 * @property ProductType $type
 * @property Variant[]|array $variants an array of the product's variants
 * @property-read string $defaultPriceAsCurrency
 * @property float|null $defaultPrice
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Product extends Element
{
    public const STATUS_LIVE = 'live';
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Product');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('commerce', 'product');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce', 'Products');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('commerce', 'products');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'product';
    }

    /**
     * @inheritdoc
     */
    public static function hasDrafts(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('commerce', 'Live'),
            self::STATUS_PENDING => Craft::t('commerce', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('commerce', 'Expired'),
            self::STATUS_DISABLED => Craft::t('commerce', 'Disabled'),
        ];
    }

    /**
     * @inheritdoc
     * @return ProductQuery The newly created [[ProductQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new ProductQuery(static::class);
    }

    /**
     * @inheritdoc
     * @return ProductCondition
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ProductCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        if ($context == 'index') {
            $productTypes = Plugin::getInstance()->getProductTypes()->getEditableProductTypes();
            $editable = true;
        } else {
            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
            $editable = false;
        }

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            $productTypeIds[] = $productType->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('commerce', 'All products'),
                'criteria' => [
                    'typeId' => $productTypeIds,
                    'editable' => $editable,
                ],
                'defaultSort' => ['postDate', 'desc'],
            ],
        ];

        $sources[] = ['heading' => Craft::t('commerce', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:' . $productType->uid;
            $canEditProducts = Craft::$app->getUser()->checkPermission('commerce-editProductType:' . $productType->uid);

            $sources[$key] = [
                'key' => $key,
                'label' => Craft::t('site', $productType->name),
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => $canEditProducts,
                ],
                'criteria' => ['typeId' => $productType->id, 'editable' => $editable],
                // Get site ids enabled for this product type
                'sites' => array_keys($productType->getSiteSettings()),
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function modifyCustomSource(array $config): array
    {
        try {
            /** @var ProductCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($config['condition']);
        } catch (InvalidConfigException) {
            return $config;
        }

        $rules = $condition->getConditionRules();

        // see if it's limited to one product type
        /** @var ProductTypeConditionRule|null $productTypeRule */
        $productTypeRule = ArrayHelper::firstWhere($rules, fn($rule) => $rule instanceof ProductTypeConditionRule);
        $productTypeOptions = $productTypeRule?->getValues();

        if ($productTypeOptions && count($productTypeOptions) === 1) {
            $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByUid(reset($productTypeOptions));
            if ($productType) {
                $config['data']['handle'] = $productType->handle;
            }
        }

        return $config;
    }

    /**
     * @inheritdoc
     */
    protected static function defineFieldLayouts(?string $source): array
    {
        if ($source === null || $source === '*') {
            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        } else {
            $productTypes = [];
            if (preg_match('/^productType:(.+)$/', $source, $matches)) {
                $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByUid($matches[1]);
                if ($productType) {
                    $productTypes[] = $productType;
                }
            }
        }

        return array_map(fn(ProductType $productType) => $productType->getFieldLayout(), $productTypes);
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        // Get the section(s) we need to check permissions on
        switch ($source) {
            case '*':
            {
                $productTypes = Plugin::getInstance()->getProductTypes()->getEditableProductTypes();
                break;
            }
            default:
            {
                if (preg_match('/^productType:(\d+)$/', $source, $matches)) {
                    $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById((int)$matches[1]);

                    if ($productType) {
                        $productTypes = [$productType];
                    }
                } elseif (preg_match('/^productType:(.+)$/', $source, $matches)) {
                    $productType = Plugin::getInstance()->getProductTypes()->getProductTypeByUid($matches[1]);

                    if ($productType) {
                        $productTypes = [$productType];
                    }
                }
            }
        }

        $actions = [];

        // Copy Reference Tag
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => CopyReferenceTag::class,
        ]);

        // Restore
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Restore::class,
            'successMessage' => Craft::t('commerce', 'Products restored.'),
            'partialSuccessMessage' => Craft::t('commerce', 'Some products restored.'),
            'failMessage' => Craft::t('commerce', 'Products not restored.'),
        ]);

        if ($source === '*') {
            // Delete
            $actions[] = Delete::class;
        } elseif (!empty($productTypes)) {
            $userSession = Craft::$app->getUser();

            $currentUser = $userSession->getIdentity();

            foreach ($productTypes as $productType) {
                $canDelete = Plugin::getInstance()->getProductTypes()->hasPermission($currentUser, $productType, 'commerce-deleteProducts');
                $canCreate = Plugin::getInstance()->getProductTypes()->hasPermission($currentUser, $productType, 'commerce-createProducts');
                $canEdit = Plugin::getInstance()->getProductTypes()->hasPermission($currentUser, $productType, 'commerce-editProductType');

                if ($canCreate) {
                    // Duplicate
                    $actions[] = Duplicate::class;
                }

                if ($canDelete) {
                    // Allow deletion
                    $deleteAction = Craft::$app->getElements()->createAction([
                        'type' => Delete::class,
                        'confirmationMessage' => Craft::t('commerce', 'Are you sure you want to delete the selected product and its variants?'),
                        'successMessage' => Craft::t('commerce', 'Products and Variants deleted.'),
                    ]);
                    $actions[] = $deleteAction;
                }

                if ($canEdit) {
                    $actions[] = SetStatus::class;
                }
            }

            if ($userSession->checkPermission('commerce-managePromotions')) {
                if (Plugin::getInstance()->getSales()->canUseSales()) {
                    $actions[] = CreateSale::class;
                }

                $actions[] = CreateDiscount::class;
            }
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title'),
            [
                'label' => Craft::t('commerce', 'Post Date'),
                'orderBy' => 'postDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('commerce', 'Expiry Date'),
                'orderBy' => 'expiryDate',
                'defaultDir' => 'desc',
            ],
            'promotable' => Craft::t('commerce', 'Promotable?'),
            'defaultPrice' => Craft::t('commerce', 'Price'),
            'defaultSku' => Craft::t('commerce', 'SKU'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('commerce', 'Product')],
            'status' => ['label' => Craft::t('commerce', 'Status')],
            'id' => ['label' => Craft::t('commerce', 'ID')],
            'type' => ['label' => Craft::t('commerce', 'Type')],
            'slug' => ['label' => Craft::t('commerce', 'Slug')],
            'uri' => ['label' => Craft::t('commerce', 'URI')],
            'postDate' => ['label' => Craft::t('commerce', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('commerce', 'Expiry Date')],
            'stock' => ['label' => Craft::t('commerce', 'Stock')],
            'link' => ['label' => Craft::t('commerce', 'Link'), 'icon' => 'world'],
            'dateCreated' => ['label' => Craft::t('commerce', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('commerce', 'Date Updated')],
            'defaultPrice' => ['label' => Craft::t('commerce', 'Price')],
            'defaultSku' => ['label' => Craft::t('commerce', 'SKU')],
            'defaultWeight' => ['label' => Craft::t('commerce', 'Weight')],
            'defaultLength' => ['label' => Craft::t('commerce', 'Length')],
            'defaultWidth' => ['label' => Craft::t('commerce', 'Width')],
            'defaultHeight' => ['label' => Craft::t('commerce', 'Height')],
            'variants' => ['label' => Craft::t('commerce', 'Variants')],
        ];
    }

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle == 'variants') {
            $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');
            $map = (new Query())
                ->select('ownerId as source, elementId as target')
                ->from(\craft\db\Table::ELEMENTS_OWNERS)
                ->where(['ownerId' => $sourceElementIds])
                ->orderBy('sortOrder asc')
                ->all();

            return [
                'elementType' => Variant::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     * @since 3.0
     */
    public static function gqlTypeNameByContext(mixed $context): string
    {
        /** @var ProductType $context */
        return $context->handle . '_Product';
    }

    /**
     * @inheritdoc
     * @since 3.0
     */
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var ProductType $context */
        return ['productTypes.' . $context->uid];
    }

    /**
     * @inheritdoc
     */
    public static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        if ($attribute === 'variants') {
            $elementQuery->andWith('variants');
        } else {
            parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }

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

    /**
     * @var int|null defaultVariantId
     */
    public ?int $defaultVariantId = null;

    /**
     * @var string|null Default SKU
     */
    public ?string $defaultSku = null;

    /**
     * @var float|null Default price
     * @see getDefaultPrice()
     * @see setDefaultPrice()
     */
    private ?float $_defaultPrice = null;

    /**
     * @var float|null Default height
     */
    public ?float $defaultHeight = null;

    /**
     * @var float|null Default length
     */
    public ?float $defaultLength = null;

    /**
     * @var float|null Default width
     */
    public ?float $defaultWidth = null;

    /**
     * @var float|null Default weight
     */
    public ?float $defaultWeight = null;

    /**
     * @var TaxCategory|null Tax category
     */
    public ?TaxCategory $taxCategory = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var VariantCollection|null This product’s variants
     */
    private ?VariantCollection $_variants = null;

    /**
     * @var NestedElementManager|null
     * @since 5.0.0
     */
    private ?NestedElementManager $_variantManager = null;

    /**
     * @throws InvalidConfigException
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::class,
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
            ],
        ];

        return $behaviors;
    }

    /**
     * @param float|null $defaultPrice
     * @return void
     * @since 5.0.11
     */
    public function setDefaultPrice(?float $defaultPrice): void
    {
        $this->_defaultPrice = $defaultPrice;
    }

    /**
     * @return float|null
     * @throws InvalidConfigException
     * @since 5.0.11
     */
    public function getDefaultPrice(): ?float
    {
        return $this->_defaultPrice ?? $this->getDefaultVariant()?->price;
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getDefaultPriceAsCurrency(): string
    {
        return $this->getDefaultVariant()?->priceAsCurrency ?? '';
    }

    public function canCreateDrafts(User $user): bool
    {
        // Everyone with view permissions can create drafts
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasRevisions(): bool
    {
        return $this->getType()->enableVersioning;
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('commerce/products');
    }

    /**
     * @inheritdoc
     */
    protected function cpRevisionsUrl(): ?string
    {
        return sprintf('%s/revisions', $this->cpEditUrl());
    }

    /**
     * @inheritdoc
     */
    public function getIsTitleTranslatable(): bool
    {
        return ($this->getType()->productTitleTranslationMethod !== Field::TRANSLATION_METHOD_NONE);
    }

    /**
     * @inheritdoc
     */
    public function getTitleTranslationDescription(): ?string
    {
        return ElementHelper::translationDescription($this->getType()->productTitleTranslationMethod);
    }

    /**
     * @inheritdoc
     */
    public function getTitleTranslationKey(): string
    {
        $type = $this->getType();
        return ElementHelper::translationKey($this, $type->productTitleTranslationMethod, $type->productTitleTranslationKeyFormat);
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return (string)$this->title;
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('commerce-editProductType:' . $productType->uid);
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('commerce-editProductType:' . $productType->uid);
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('commerce-editProductType:' . $productType->uid);
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        try {
            $productType = $this->getType();
        } catch (\Exception) {
            return false;
        }

        return $user->can('commerce-deleteProducts:' . $productType->uid);
    }

    /**
     * @inheritdoc
     */
    public function canDeleteForSite(User $user): bool
    {
        return Craft::$app->getElements()->canDelete($this, $user);
    }

    /**
     * @inheritdoc
     */
    public function createAnother(): ?ElementInterface
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    protected function crumbs(): array
    {
        $productType = $this->getType();

        $productTypes = Collection::make(Plugin::getInstance()->getProductTypes()->getEditableProductTypes());
        /** @var Collection $productTypeOptions */
        $productTypeOptions = $productTypes
            ->map(fn(ProductType $t) => [
                'label' => Craft::t('site', $t->name),
                'url' => "commerce/products/$t->handle",
                'selected' => $t->id === $productType->id,
            ]);

        return [
            [
                'label' => Craft::t('commerce', 'Products'),
                'url' => 'commerce/products',
            ],
            [
                'menu' => [
                    'label' => Craft::t('commerce', 'Select product type'),
                    'items' => $productTypeOptions->all(),
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function uiLabel(): ?string
    {
        if (!isset($this->title) || trim($this->title) === '') {
            return Craft::t('app', 'Untitled {type}', [
                'type' => self::lowerDisplayName(),
            ]);
        }

        return null;
    }

    /**
     * Returns the product's product type.
     *
     * @throws InvalidConfigException
     */
    public function getType(): ProductType
    {
        if ($this->typeId === null) {
            throw new InvalidConfigException('Product is missing its product type ID');
        }

        $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($this->typeId);

        if ($productType === null) {
            throw new InvalidConfigException('Invalid product type ID: ' . $this->typeId);
        }

        return $productType;
    }

    public function getName(): ?string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    protected function cacheTags(): array
    {
        return [
            "productType:$this->typeId",
        ];
    }

    /**
     * @inheritdoc
     */
    public function getUriFormat(): ?string
    {
        $productTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($productTypeSiteSettings[$this->siteId])) {
            throw new InvalidConfigException('The "' . $this->getType()->name . '" product type is not enabled for the "' . $this->getSite()->name . '" site.');
        }

        return $productTypeSiteSettings[$this->siteId]->uriFormat;
    }

    /**
     * @inheritdoc
     */
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
     * @param bool $includeDisabled
     * @return Variant|null
     * @throws InvalidConfigException
     */
    public function getDefaultVariant(bool $includeDisabled = false): ?Variant
    {
        $defaultVariant = $this->getVariants($includeDisabled)->firstWhere('id', $this->defaultVariantId);

        return $defaultVariant ?: $this->getVariants($includeDisabled)->first();
    }

    /**
     * Return the cheapest variant.
     *
     * @throws InvalidConfigException
     * @noinspection PhpUnused
     */
    public function getCheapestVariant(bool $includeDisabled = false): ?Variant
    {
        return $this->getVariants($includeDisabled)->cheapest();
    }

    /**
     * Returns an array of the product's variants.
     *
     * @param bool $includeDisabled
     * @return VariantCollection
     * @throws InvalidConfigException
     */
    public function getVariants(bool $includeDisabled = false): VariantCollection
    {
        if (!isset($this->_variants)) {
            if (!$this->id) {
                return VariantCollection::make();
            }

            $this->_variants = self::createVariantQuery($this)->status(null)->collect();
        }

        return $this->_variants->filter(fn(Variant $variant) => $includeDisabled || $variant->enabled);
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        if (!isset($this->typeId)) {
            throw new InvalidConfigException('Require `typeId` must be set on the product.');
        }

        $productType = $this->getType();
        /** @var Site[] $allSites */
        $allSites = ArrayHelper::index(Craft::$app->getSites()->getAllSites(true), 'id');
        $sites = [];

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
                    ->select('elements_sites.siteId')
                    ->drafts(null)
                    ->provisionalDrafts(null)
                    ->revisions($this->getIsRevision())
                    ->column();
            } else {
                $currentSites = [];
            }

            // If this is being duplicated from another element (e.g. a draft), include any sites the source element is saved to as well
            if (!empty($this->duplicateOf->id)) {
                array_push($currentSites, ...self::find()
                    ->status(null)
                    ->id($this->duplicateOf->id)
                    ->site('*')
                    ->select('elements_sites.siteId')
                    ->drafts(null)
                    ->provisionalDrafts(null)
                    ->revisions($this->duplicateOf->getIsRevision())
                    ->column()
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
     * @param VariantCollection|VariantQuery|array $variants
     */
    public function setVariants(VariantCollection|VariantQuery|array $variants): void
    {
        if ($variants instanceof VariantQuery) {
            // just unset our existing records
            $this->_variants = null;
            return;
        }

        $this->_variants = $variants instanceof VariantCollection ? $variants : VariantCollection::make($variants);
    }

    /**
     * Returns a nested element manager for the product’s variants.
     *
     * @return NestedElementManager
     * @since 5.0.0
     */
    public function getVariantManager(): NestedElementManager
    {
        if (!isset($this->_variantManager)) {
            $this->_variantManager = new NestedElementManager(
                Variant::class,
                /** @phpstan-ignore-next-line */
                fn(Product $product) => self::createVariantQuery($product),
                [
                    'attribute' => 'variants',
                    'propagationMethod' => $this->getType()->propagationMethod,
                    'valueGetter' => fn() => $this->getVariants(true),
                ],
            );
        }

        return $this->_variantManager;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status == self::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
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
     * @throws InvalidConfigException
     * @noinspection PhpUnused
     */
    public function getTotalStock(bool $includeDisabled = false): int
    {
        $stock = 0;
        foreach ($this->getVariants($includeDisabled) as $variant) {
            $stock += $variant->getStock();
        }

        return $stock;
    }

    /**
     * Returns whether at least one variant has unlimited stock.
     *
     * @throws InvalidConfigException
     * @deprecated in 5.0.0 and will be removed in 6.0.0. Check each variant instead.
     */
    public function getHasUnlimitedStock(bool $includeDisabled = false): bool
    {
        foreach ($this->getVariants($includeDisabled) as $variant) {
            if (!$variant->inventoryTracked) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     * @since 3.0
     */
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this->getType());
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle == 'variants') {
            /** @var Variant[] $elements */
            $this->setVariants($elements);
        } else {
            parent::setEagerLoadedElements($handle, $elements, $plan);
        }
    }

    /**
     * @inheritdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [];
        $view = Craft::$app->getView();

        // Slug
        $fields[] = $this->slugFieldHtml($static);

        $isDeltaRegistrationActive = $view->getIsDeltaRegistrationActive();
        $view->setIsDeltaRegistrationActive(true);
        $view->registerDeltaName('postDate');
        $view->registerDeltaName('expiryDate');
        $view->setIsDeltaRegistrationActive($isDeltaRegistrationActive);

        // Post Date
        $fields[] = Cp::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('postDate'),
            'label' => Craft::t('app', 'Post Date'),
            'id' => 'postDate',
            'name' => 'postDate',
            'value' => $this->_userPostDate(),
            'errors' => $this->getErrors('postDate'),
            'disabled' => $static,
        ]);

        // Expiry Date
        $fields[] = Cp::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('expiryDate'),
            'label' => Craft::t('app', 'Expiry Date'),
            'id' => 'expiryDate',
            'name' => 'expiryDate',
            'value' => $this->expiryDate,
            'errors' => $this->getErrors('expiryDate'),
            'disabled' => $static,
        ]);

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    /**
     * Returns the Post Date value that should be shown on the edit form.
     *
     * @return DateTime|null
     */
    private function _userPostDate(): ?DateTime
    {
        if (!$this->postDate || ($this->getIsUnpublishedDraft() && $this->postDate == $this->dateCreated)) {
            // Pretend the post date hasn't been set yet, even if it has
            return null;
        }

        return $this->postDate;
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(): array
    {
        $metadata = parent::getMetadata();

        if (array_key_exists(Craft::t('app', 'Status'), $metadata)) {
            unset($metadata[Craft::t('app', 'Status')]);
        }

        return $metadata;
    }

    /**
     * @inheritDoc
     */
    protected function searchKeywords(string $attribute): string
    {
        if ($attribute === 'sku') {
            return $this->getVariants()->only('sku')->implode(' ');
        }

        return parent::searchKeywords($attribute);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if (!$isNew) {
                $record = ProductRecord::findOne($this->id);

                if (!$record) {
                    throw new Exception('Invalid product ID: ' . $this->id);
                }
            } else {
                $record = new ProductRecord();
                $record->id = $this->id;
            }

            $record->postDate = $this->postDate;
            $record->expiryDate = $this->expiryDate;
            $record->typeId = $this->typeId;

            $defaultVariant = $this->getDefaultVariant();
            $record->defaultVariantId = $defaultVariant->id ?? null;
            $record->defaultSku = $defaultVariant->skuAsText ?? '';
            $record->defaultPrice = $defaultVariant->price ?? 0.0;
            $record->defaultHeight = $defaultVariant->height ?? 0.0;
            $record->defaultLength = $defaultVariant->length ?? 0.0;
            $record->defaultWidth = $defaultVariant->width ?? 0.0;
            $record->defaultWeight = $defaultVariant->weight ?? 0.0;

            // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
            $record->dateUpdated = $this->dateUpdated;
            $record->dateCreated = $this->dateCreated;

            $record->save(false);

            $this->id = $record->id;
        }

        parent::afterSave($isNew);
    }

    /**
     * Updates the entry's title, if its entry type has a dynamic title format.
     *
     * @since 3.0.3
     * @see \craft\elements\Entry::updateTitle
     */
    public function updateTitle(): void
    {
        $productType = $this->getType();

        // check for null just incase the value comes back as 1, 0, true or false
        if (!$productType->hasProductTitleField && $productType->hasProductTitleField !== null) {
            // Make sure that the locale has been loaded in case the title format has any Date/Time fields
            Craft::$app->getLocale();
            // Set Craft to the entry's site's language, in case the title format has any static translations
            $language = Craft::$app->language;
            Craft::$app->language = $this->getSite()->language;
            $title = Craft::$app->getView()->renderObjectTemplate($productType->productTitleFormat, $this);
            if ($title !== '') {
                $this->title = $title;
            }
            Craft::$app->language = $language;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        // We need to generate all variant sku formats before validating the product,
        // since the product validates the uniqueness of all variants in memory.
        $type = $this->getType();
        foreach ($this->getVariants(true) as $variant) {
            if (!$variant->sku && $type->skuFormat) {
                try {
                    $variant->sku = Craft::$app->getView()->renderObjectTemplate($type->skuFormat, $variant);
                } catch (\Exception $e) {
                    Craft::error('Craft Commerce could not generate the supplied SKU format: ' . $e->getMessage(), __METHOD__);
                    $variant->sku = '';
                }
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        $this->getVariantManager()->deleteNestedElements($this, $this->hardDelete);

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['typeId'], 'number', 'integerOnly' => true],
            [['postDate', 'expiryDate'], DateTimeValidator::class],
            [['defaultPrice'], 'safe'],
            [
                ['variants'],
                function() {
                    if ($this->getVariants(true)->isEmpty()) {
                        $this->addError('variants', Craft::t('commerce', 'Must have at least one variant.'));
                    }
                },
                'skipOnEmpty' => false,
                'on' => self::SCENARIO_LIVE,
            ],
            [
                ['variants'],
                function() {
                    $skus = [];
                    foreach ($this->getVariants(true) as $variant) {
                        if (isset($skus[$variant->sku])) {
                            $this->addError('variants', Craft::t('commerce', 'Not all SKUs are unique.'));
                            break;
                        }
                        $skus[$variant->sku] = true;
                    }
                },
                'on' => self::SCENARIO_LIVE,
            ],
            [
                ['variants'],
                function() {
                    if ($this->getType()->maxVariants) {
                        $variantCount = count($this->getVariants(true));
                        if ($variantCount > $this->getType()->maxVariants) {
                            $this->addError('variants', Craft::t('commerce', 'Too many variants for this product.'));
                        }
                    }
                },
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        // this is needed for Craft.NestedElementManager::markAsDirty()
        if (isset($values['variants']) && $values['variants'] === '*') {
            $this->setDirtyAttributes(['variants']);
            unset($values['variants']);
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        $fieldLayout = parent::getFieldLayout();
        if ($fieldLayout) {
            return $fieldLayout;
        }

        $fieldLayout = $this->getType()->getFieldLayout();
        if ($fieldLayout->id) {
            $this->fieldLayoutId = $fieldLayout->id;
            return $fieldLayout;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // Make sure the entry has at least one revision if the section has versioning enabled
        if ($this->_shouldSaveRevision()) {
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
                    $revisionNotes = 'Revision from ' . Craft::$app->getFormatter()->asDatetime($currentProduct->dateUpdated);
                    Craft::$app->getRevisions()->createRevision($currentProduct, notes: $revisionNotes);
                }
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

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'defaultSku',
            'sku',
        ];
    }

    /**
     * @param Product $product
     * @return VariantQuery
     */
    private static function createVariantQuery(Product $product): VariantQuery
    {
        return Variant::find()
            ->productId($product->id)
            ->siteId($product->siteId)
            ->orderBy(['sortOrder' => SORT_ASC]);
    }

    /**
     * @inheritdoc
     */
    protected function route(): array|string|null
    {
        // Make sure that the product is actually live
        if (!$this->previewing && $this->getStatus() != self::STATUS_LIVE) {
            return null;
        }

        // Make sure the product type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
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

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        $productType = $this->getType();

        switch ($attribute) {
            case 'type':
            {
                return Craft::t('site', Html::encode($productType->name));
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
                return $this->defaultPriceAsCurrency;
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
                return $hasUnlimited ? '∞' . ($stock ? ' & ' . $stock : '') : ($stock ?: '0');
            }
            case 'defaultWeight':
            {
                if ($productType->hasDimensions) {
                    return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->weightUnits;
                }

                return '';
            }
            case 'defaultLength':
            case 'defaultWidth':
            case 'defaultHeight':
            {
                if ($productType->hasDimensions) {
                    return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits;
                }

                return '';
            }
            case 'variants':
            {
                $value = $this->getVariants(true);
                /** @var Variant|null $first */
                $first = $value->first();
                $html = $first ? Cp::elementChipHtml($first) : '';

                if ($value->isNotEmpty() && $value->count() > 1) {
                    $otherItems = $value->filter(fn($v, $k) => $k > 0);
                    $otherHtml = $otherItems->map(function($v) {
                        return Cp::elementChipHtml($v);
                    })->join('');

                    $html .= Html::tag('span', '+' . Craft::$app->getFormatter()->asInteger($otherItems->count()), [
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

    /**
     * @inheritDoc
     */
    public function setScenario($value): void
    {
        foreach ($this->getVariants() as $variant) {
            $variant->setScenario($value);
        }

        parent::setScenario($value);
    }

    /**
     * @inheritDoc
     */
    public function afterPropagate(bool $isNew): void
    {
        $this->getVariantManager()->maintainNestedElements($this, $isNew);
        parent::afterPropagate($isNew);

        // Save a new revision?
        if ($this->_shouldSaveRevision()) {
            Craft::$app->getRevisions()->createRevision($this, notes: $this->revisionNotes);
        }
    }

    /**
     * Returns whether the product should be saving revisions on save.
     *
     * @return bool
     */
    private function _shouldSaveRevision(): bool
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
