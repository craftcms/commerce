<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Elements;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Element\Actions\Duplicate;
use CraftCms\Cms\Element\Actions\Restore;
use CraftCms\Cms\Element\Actions\SetStatus;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Data\EagerLoadPlan;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Facades\ElementActions;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Sequence;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Controls\DateTime as DateTimeControl;
use CraftCms\Cms\Ui\Controls\Slug;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Models\DigitalProduct as DigitalProductRecord;
use CraftCms\Commerce\DigitalProduct\Queries\DigitalProductQuery;
use CraftCms\Commerce\DigitalProduct\Validation\DigitalProductRules;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Override;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;

/**
 * A digital product: a purchasable with no inventory and no shipping, which issues a {@see License}
 * for each unit sold.
 *
 * @property-read DigitalProductType $type
 * @property-read License[] $existingLicenses
 */
#[Ruleset(DigitalProductRules::class)]
class DigitalProduct extends Purchasable
{
    public const string STATUS_LIVE = 'live';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_EXPIRED = 'expired';

    public ?int $typeId = null;

    public ?DateTime $postDate = null;

    public ?DateTime $expiryDate = null;

    /**
     * Digital products don't need to be shipped.
     */
    #[Override]
    public bool $freeShipping = true;

    /** @var License[]|null */
    private ?array $_existingLicenses = null;

    #[Override]
    public static function displayName(): string
    {
        return t('Digital Product', category: 'commerce');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('digital product', category: 'commerce');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Digital Products', category: 'commerce');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('digital products', category: 'commerce');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'digitalProduct';
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
    public static function hasInventory(): bool
    {
        return false;
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

    #[Override]
    public static function find(): DigitalProductQuery
    {
        return new DigitalProductQuery();
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        $typesService = app(DigitalProductTypes::class);

        if ($context === 'index') {
            $types = $typesService->getEditableDigitalProductTypes();
            $editable = true;
        } else {
            $types = $typesService->getAllDigitalProductTypes();
            $editable = null;
        }

        $sources = [
            [
                'key' => '*',
                'label' => t('All digital products', category: 'commerce'),
                'criteria' => [
                    'typeId' => array_map(fn(DigitalProductType $type) => $type->id, $types),
                    'editable' => $editable,
                ],
                'defaultSort' => ['postDate', 'desc'],
            ],
        ];

        $sources[] = ['heading' => t('Digital Product Types', category: 'commerce')];

        foreach ($types as $type) {
            // `productType:` matches the Digital Products plugin's source keys, so saved sources keep working after migrating.
            $key = 'productType:' . $type->uid;

            $sources[$key] = [
                'key' => $key,
                'label' => t($type->name, category: 'site'),
                'data' => [
                    'handle' => $type->handle,
                    'editable' => (bool)currentUser()?->can('commerce-manageDigitalProductType:' . $type->uid),
                ],
                'criteria' => [
                    'typeId' => $type->id,
                    'editable' => $editable,
                ],
                'sites' => $type->getSiteIds(),
                'defaultSort' => ['postDate', 'desc'],
            ];
        }

        return $sources;
    }

    /**
     * Digital product type sources have index URLs of their own (`commerce/digital-products/{handle}`).
     */
    #[Override]
    public static function sourceCpUri(array $source, ?string $page = null): ?string
    {
        $handle = $source['data']['handle'] ?? null;

        if (!str_starts_with((string)($source['key'] ?? ''), 'productType:') || !is_string($handle) || $handle === '') {
            return null;
        }

        return "commerce/digital-products/$handle";
    }

    #[Override]
    protected static function defineFieldLayouts(?string $source): array
    {
        $typesService = app(DigitalProductTypes::class);

        if ($source === null || $source === '*') {
            $types = $typesService->getAllDigitalProductTypes();
        } elseif (preg_match('/^productType:(.+)$/', $source, $matches) && ($type = $typesService->getDigitalProductTypeByUid($matches[1]))) {
            $types = [$type];
        } else {
            $types = [];
        }

        return array_map(fn(DigitalProductType $type) => $type->getFieldLayout(), $types);
    }

    #[Override]
    protected static function defineActions(string $source): array
    {
        $typesService = app(DigitalProductTypes::class);

        if ($source === '*') {
            $types = $typesService->getEditableDigitalProductTypes();
        } elseif (preg_match('/^productType:(.+)$/', $source, $matches) && ($type = $typesService->getDigitalProductTypeByUid($matches[1]))) {
            $types = [$type];
        } else {
            $types = [];
        }

        $actions = [
            ElementActions::createAction([
                'type' => Restore::class,
                'successMessage' => t('Digital products restored.', category: 'commerce'),
                'partialSuccessMessage' => t('Some digital products restored.', category: 'commerce'),
                'failMessage' => t('Digital products not restored.', category: 'commerce'),
            ], static::class),
        ];

        $canManage = !empty($types) && collect($types)->every(
            fn(DigitalProductType $type) => (bool)currentUser()?->can('commerce-manageDigitalProductType:' . $type->uid),
        );

        if ($canManage) {
            $actions[] = [
                'type' => Duplicate::class,
                'asDrafts' => true,
            ];
            $actions[] = ElementActions::createAction([
                'type' => Delete::class,
                'confirmationMessage' => t('Are you sure you want to delete the selected digital products?', category: 'commerce'),
                'successMessage' => t('Digital products deleted.', category: 'commerce'),
            ], static::class);
            $actions[] = SetStatus::class;
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
            'sku' => t('SKU', category: 'commerce'),
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
            'title' => ['label' => t('Title', category: 'commerce')],
            'status' => ['label' => t('Status', category: 'commerce')],
            'type' => ['label' => t('Type', category: 'commerce')],
            'slug' => ['label' => t('Slug', category: 'commerce')],
            'uri' => ['label' => t('URI', category: 'commerce')],
            'sku' => ['label' => t('SKU', category: 'commerce')],
            'price' => ['label' => t('Price', category: 'commerce')],
            'promotionalPrice' => ['label' => t('Promotional Price', category: 'commerce')],
            'promotable' => ['label' => t('Promotable', category: 'commerce')],
            'availableForPurchase' => ['label' => t('Available for purchase', category: 'commerce')],
            'postDate' => ['label' => t('Post Date', category: 'commerce')],
            'expiryDate' => ['label' => t('Expiry Date', category: 'commerce')],
            'link' => ['label' => t('Link', category: 'commerce'), 'icon' => 'world'],
            'id' => ['label' => t('ID', category: 'commerce')],
            'dateCreated' => ['label' => t('Date Created', category: 'commerce')],
            'dateUpdated' => ['label' => t('Date Updated', category: 'commerce')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            ...($source === '*' ? ['type'] : []),
            'status',
            'sku',
            'price',
            'postDate',
            'expiryDate',
        ];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return ['sku'];
    }

    #[Override]
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle === 'existingLicenses') {
            $userId = currentUserElement()?->id;

            if (!$userId) {
                return ['elementType' => License::class, 'map' => []];
            }

            $map = DB::table(Table::LICENSES)
                ->select(['productId as source', 'id as target'])
                ->whereIn('productId', array_filter(array_map(fn(ElementInterface $element) => $element->id, $sourceElements)))
                ->where('userId', $userId)
                ->get()
                ->map(fn(object $row) => (array)$row)
                ->all();

            return [
                'elementType' => License::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    #[Override]
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle === 'existingLicenses') {
            /** @var License[] $elements */
            $this->_existingLicenses = $elements;

            return;
        }

        parent::setEagerLoadedElements($handle, $elements, $plan);
    }

    public static function gqlTypeNameByContext(mixed $context): string
    {
        /** @var DigitalProductType $context */
        return $context->handle . '_Product';
    }

    #[Override]
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var DigitalProductType $context */
        return ['digitalProductTypes.' . $context->uid];
    }

    #[Override]
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this->getType());
    }

    #[Override]
    public function __toString(): string
    {
        return (string)$this->title;
    }

    public function getName(): ?string
    {
        return $this->title;
    }

    /**
     * Returns the digital product's type.
     *
     * @throws \RuntimeException
     */
    public function getType(): DigitalProductType
    {
        if ($this->typeId === null) {
            throw new \RuntimeException('Digital product is missing its type ID');
        }

        return app(DigitalProductTypes::class)->getDigitalProductTypeById($this->typeId)
            ?? throw new \RuntimeException('Invalid digital product type ID: ' . $this->typeId);
    }

    /**
     * Returns the current user’s licenses for this digital product.
     *
     * @return License[]
     */
    public function getExistingLicenses(): array
    {
        if ($this->_existingLicenses !== null) {
            return $this->_existingLicenses;
        }

        $userId = currentUserElement()?->id;

        if (!$userId || !$this->id) {
            return [];
        }

        /** @var License[] $licenses */
        $licenses = License::find()
            ->productId($this->id)
            ->ownerId($userId)
            ->all();

        return $this->_existingLicenses = $licenses;
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        try {
            return $this->getType()->getFieldLayout();
        } catch (\RuntimeException) {
            // The type was probably deleted
            return null;
        }
    }

    #[Override]
    public function getSupportedSites(): array
    {
        return array_map(fn(int $siteId) => [
            'siteId' => $siteId,
            'propagate' => true,
            'enabledByDefault' => true,
        ], $this->getType()->getSiteIds());
    }

    #[Override]
    public function getUriFormat(): ?string
    {
        $siteSettings = $this->getType()->getSiteSettings()[$this->siteId] ?? null;

        if (!$siteSettings) {
            throw new \RuntimeException('The "' . $this->getType()->name . '" digital product type is not enabled for the "' . $this->getSite()->name . '" site.');
        }

        return $siteSettings->hasUrls ? $siteSettings->uriFormat : null;
    }

    #[Override]
    protected function route(): array|string|null
    {
        if (!$this->previewing && $this->getStatus() !== self::STATUS_LIVE) {
            return null;
        }

        $siteSettings = $this->getType()->getSiteSettings()[Sites::getCurrentSite()->id] ?? null;

        if (!$siteSettings || !$siteSettings->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => (string)$siteSettings->template,
                'variables' => [
                    'product' => $this,
                ],
            ],
        ];
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        $path = sprintf('commerce/digital-products/%s/%s', $this->getType()->handle, $this->getCanonicalId());

        // Ignore homepage/temp slugs
        if ($this->slug && !str_starts_with($this->slug, '__')) {
            $path .= sprintf('-%s', str_replace('/', '-', $this->slug));
        }

        return $path;
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('commerce/digital-products/' . $this->getType()->handle);
    }

    /**
     * @return list<ActionItem>
     */
    #[Override]
    protected function crumbs(): array
    {
        $type = $this->getType();

        $typeOptions = collect(app(DigitalProductTypes::class)->getEditableDigitalProductTypes())
            ->map(fn(DigitalProductType $t) => [
                'type' => 'link',
                'label' => t($t->name, category: 'site'),
                'href' => Url::cpUrl("commerce/digital-products/$t->handle"),
                'selected' => $t->id === $type->id,
            ])
            ->values();

        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            new ActionItem()->label(static::pluralDisplayName())->href(Url::cpUrl('commerce/digital-products')),
            new ActionItem()
                ->label(t($type->name, category: 'site'))
                ->href(Url::cpUrl("commerce/digital-products/$type->handle"))
                ->items($typeOptions->count() > 1 ? $typeOptions->all() : []),
        ];
    }

    #[Override]
    protected function cacheTags(): array
    {
        return [
            "digitalProductType:$this->typeId",
        ];
    }

    #[Override]
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_ENABLED && $this->postDate) {
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

    #[Override]
    public function getIsAvailable(): bool
    {
        return $this->availableForPurchase
            && $this->getStatus() === self::STATUS_LIVE
            && !PurchasableHelper::isTempSku($this->getSku());
    }

    #[Override]
    public function getDescription(): string
    {
        return (string)$this->title;
    }

    #[Override]
    public function hasFreeShipping(): bool
    {
        return true;
    }

    #[Override]
    public function getIsShippable(): bool
    {
        return false;
    }

    #[Override]
    public function getSnapshot(): array
    {
        return [
            ...parent::getSnapshot(),
            'type' => $this->typeId ? $this->getType()->handle : null,
        ];
    }

    /**
     * Declared alongside {@see metaFieldsNodes()}, which the element editor only uses when both are declared by the same class.
     */
    #[Override]
    protected function metaFieldsHtml(bool $static): string
    {
        return parent::metaFieldsHtml($static);
    }

    /**
     * @return list<Node>
     */
    #[Override]
    protected function metaFieldsNodes(bool $static): array
    {
        $mode = $static ? ControlMode::Disabled : ControlMode::Editable;

        return [
            Field::make(t('Slug'))->control(
                Slug::make('slug')
                    ->value(!ElementHelper::isTempSlug($this->slug) ? $this->slug : null)
                    ->source('title')
                    ->autoGenerate($this->isProvisionalDraft || $this->slug === null || ElementHelper::isTempSlug($this->slug))
                    ->mode($mode),
            ),
            Field::make(t('Post Date'))->control(
                DateTimeControl::make('postDate')
                    ->showTime()
                    ->minuteIncrement(1)
                    ->value(self::dateTimeControlValue($this->userPostDate()))
                    ->mode($mode),
            ),
            Field::make(t('Expiry Date'))->control(
                DateTimeControl::make('expiryDate')
                    ->showTime()
                    ->minuteIncrement(1)
                    ->value(self::dateTimeControlValue($this->expiryDate))
                    ->mode($mode),
            ),
            ...parent::metaFieldsNodes($static),
        ];
    }

    /**
     * @return array{date: string, time: string, timezone: string}
     */
    private static function dateTimeControlValue(?DateTimeInterface $value): array
    {
        return [
            'date' => $value?->format('Y-m-d') ?? '',
            'time' => $value?->format('H:i') ?? '',
            'timezone' => $value?->getTimezone()->getName() ?? Cms::timezone(),
        ];
    }

    /**
     * The Post Date value to show on the edit form, which is empty for new drafts.
     */
    private function userPostDate(): ?DateTime
    {
        if (!$this->postDate || ($this->getIsUnpublishedDraft() && $this->postDate == $this->dateCreated)) {
            return null;
        }

        return $this->postDate;
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'type' => t(Html::encode($this->getType()->name), category: 'site'),
            'promotable' => $this->promotable ? Html::tag('span', '', ['class' => 'checkbox-icon', 'role' => 'img', 'title' => t('Yes')]) : '',
            default => parent::attributeHtml($attribute),
        };
    }

    /**
     * Generates the SKU from the type’s SKU format when one wasn’t entered.
     */
    #[Override]
    public function prepareForValidation(): void
    {
        $skuFormat = $this->typeId ? $this->getType()->skuFormat : null;

        if (!$skuFormat || ($this->getSku() !== '' && !PurchasableHelper::isTempSku($this->getSku()))) {
            return;
        }

        // SKU formats usually include the slug, which element validation would only generate after this
        if (($this->slug === null || ElementHelper::isTempSlug($this->slug)) && $this->title) {
            $this->slug = ElementHelper::generateSlug($this->title, null, $this->getSite()->getLanguage());
        }

        // Without a title there's no real slug yet (e.g. a fresh draft), so wait for one
        if ($this->slug === null || ElementHelper::isTempSlug($this->slug)) {
            return;
        }

        try {
            $sku = renderSandboxedObjectTemplate($skuFormat, $this);
        } catch (\Throwable $e) {
            Log::error('Craft Commerce could not generate the supplied SKU format: ' . $e->getMessage());
            return;
        }

        if ($sku === '') {
            return;
        }

        $baseSku = $sku;
        while ($this->skuExists($sku)) {
            $sku = $baseSku . '-' . Sequence::next('sku::' . $baseSku);
        }

        $this->setSku($sku);
    }

    private function skuExists(string $sku): bool
    {
        return DB::table(Table::PURCHASABLES)
            ->where('sku', $sku)
            ->when($this->getCanonicalId(), fn($query, $id) => $query->where('id', '!=', $id))
            ->exists();
    }

    #[Override]
    public function beforeSave(bool $isNew): bool
    {
        $this->fieldLayoutId = $this->getType()->fieldLayoutId;

        if ($this->enabled && !$this->postDate) {
            // Default the post date to the current date/time, without the seconds
            $this->postDate = new DateTime();
            $this->postDate->setTimestamp($this->postDate->getTimestamp() - ($this->postDate->getTimestamp() % 60));
        }

        return parent::beforeSave($isNew);
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $record = $isNew ? null : DigitalProductRecord::query()->find($this->id);

            if (!$record) {
                $record = new DigitalProductRecord();
                $record->id = $this->id;
                $record->dateCreated = Query::prepareDateForDb($this->dateCreated ?? new DateTime());
            }

            $record->typeId = $this->typeId;
            $record->postDate = Query::prepareDateForDb($this->postDate);
            $record->expiryDate = Query::prepareDateForDb($this->expiryDate);
            $record->dateUpdated = Query::prepareDateForDb($this->dateUpdated ?? new DateTime());
            $record->save();
        }

        parent::afterSave($isNew);
    }
}
