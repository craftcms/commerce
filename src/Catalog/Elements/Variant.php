<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Elements;

use craft\commerce\elements\actions\SetDefaultVariant;
use craft\commerce\elements\conditions\variants\VariantCondition;
use craft\commerce\Plugin;
use CraftCms\Cms\Cp\Html\ElementHtml;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Actions\Copy;
use CraftCms\Cms\Element\Actions\Restore;
use CraftCms\Cms\Element\Concerns\NestedElement;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Contracts\NestedElementInterface;
use CraftCms\Cms\Element\Data\EagerLoadPlan;
use CraftCms\Cms\Element\ElementHelper;
use CraftCms\Cms\Field\Enums\TranslationMethod;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Facades\ElementActions;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Sequence;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Catalog\Events\CustomizeProductSnapshotDataEvent;
use CraftCms\Commerce\Catalog\Events\CustomizeProductSnapshotFieldsEvent;
use CraftCms\Commerce\Catalog\Events\CustomizeVariantSnapshotDataEvent;
use CraftCms\Commerce\Catalog\Events\CustomizeVariantSnapshotFieldsEvent;
use CraftCms\Commerce\Catalog\Models\Variant as VariantRecord;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\Queries\VariantQuery;
use CraftCms\Commerce\Catalog\Validation\VariantRules;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Purchasable as PurchasableHelper;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\Shipping\Models\ShippingCategory;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\Models\TaxCategory;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Override;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;

/**
 * Variant element.
 *
 * @property Product|null $owner
 * @property Product|null $primaryOwner
 * @property-read string[] $cacheTags
 * @property-read string $gqlTypeName
 * @property-read string $skuAsText
 * @method Product|null getOwner()
 * @method Product|null getPrimaryOwner()
 */
#[Ruleset(VariantRules::class)]
class Variant extends Purchasable implements NestedElementInterface
{
    use NestedElement {
        eagerLoadingMap as nestedEagerLoadingMap;
        setPrimaryOwner as nestedSetPrimaryOwner;
        setOwner as nestedSetOwner;
        setEagerLoadedElements as nestedSetEagerLoadedElements;
        extraFields as nestedExtraFields;
    }

    /**
     * @event CustomizeVariantSnapshotFieldsEvent The event that is triggered before a variant’s field data is captured, which makes it possible to customize which fields are included in the snapshot. Custom fields are not included by default.
     *
     * This example adds every custom field to the variant snapshot:
     *
     * ```php
     * use CraftCms\Commerce\Catalog\Elements\Variant;
     * use CraftCms\Commerce\Catalog\Events\CustomizeVariantSnapshotFieldsEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     Variant::class,
     *     Variant::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT,
     *     function(CustomizeVariantSnapshotFieldsEvent $event) {
     *         // @var Variant $variant
     *         $variant = $event->variant;
     *         // @var array|null $fields
     *         $fields = $event->fields;
     *
     *         // Add every custom field to the snapshot
     *         if (($fieldLayout = $variant->getFieldLayout()) !== null) {
     *             foreach ($fieldLayout->getFields() as $field) {
     *                 $fields[] = $field->handle;
     *             }
     *         }
     *
     *         $event->fields = $fields;
     *     }
     * );
     * ```
     */
    public const string EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT = 'beforeCaptureVariantSnapshot';

    /**
     * @event CustomizeVariantSnapshotDataEvent The event that is triggered after a variant’s field data is captured. This makes it possible to customize, extend, or redact the data to be persisted on the variant instance.
     */
    public const string EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT = 'afterCaptureVariantSnapshot';

    /**
     * @event CustomizeProductSnapshotFieldsEvent The event that is triggered before a product’s field data is captured. This makes it possible to customize which fields are included in the snapshot. Custom fields are not included by default.
     *
     * ::: warning
     * Add with care! A huge amount of custom fields/data will increase your database size.
     * :::
     */
    public const string EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT = 'beforeCaptureProductSnapshot';

    /**
     * @event CustomizeProductSnapshotDataEvent The event that is triggered after a product’s field data is captured, which can be used to customize, extend, or redact the data to be persisted on the product instance.
     */
    public const string EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT = 'afterCaptureProductSnapshot';

    public bool $isDefault = false;

    /**
     * @see getProductSlug()
     * @see setProductSlug()
     */
    private ?string $_productSlug = null;

    /**
     * @see getProductTypeHandle()
     * @see setProductTypeHandle()
     */
    private ?string $_productTypeHandle = null;

    #[Override]
    public function safeAttributes(): array
    {
        $attributes = parent::safeAttributes();
        $attributes[] = 'productId';

        return $attributes;
    }

    #[Override]
    protected function uiLabel(): ?string
    {
        $owner = $this->getOwner();

        if ($owner) {
            $uiLabelFormat = $owner->getType()->variantUiLabelFormat;
            if ($uiLabelFormat !== '{title}') {
                $uiLabel = renderSandboxedObjectTemplate($uiLabelFormat, $this);
                if ($uiLabel !== '') {
                    return $uiLabel;
                }
            }
        }

        return null;
    }

    #[Override]
    public static function displayName(): string
    {
        return t('Product Variant', category: 'commerce');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('product variant', category: 'commerce');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Product Variants', category: 'commerce');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('product variants', category: 'commerce');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'variant';
    }

    #[Override]
    public function getIsTitleTranslatable(): bool
    {
        return $this->getOwner()->getType()->variantTitleTranslationMethod !== TranslationMethod::None->value;
    }

    #[Override]
    public function getTitleTranslationDescription(): ?string
    {
        return TranslationMethod::tryFrom($this->getOwner()->getType()->variantTitleTranslationMethod)?->description();
    }

    #[Override]
    public function getTitleTranslationKey(): string
    {
        $type = $this->getOwner()->getType();

        return TranslationMethod::tryFrom($type->variantTitleTranslationMethod)
            ?->elementKey($this, $type->variantTitleTranslationKeyFormat)
            ?? (string)$this->siteId;
    }

    #[Override]
    public function canSave(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        $product = $this->getOwner();
        if ($product === null) {
            return false;
        }

        return $product->canSave($user);
    }

    #[Override]
    public function canCopy(\CraftCms\Cms\User\Elements\User $user): bool
    {
        return true;
    }

    #[Override]
    public function canDelete(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        return $this->canSave($user);
    }

    #[Override]
    public function canDuplicate(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }

        return $this->canSave($user);
    }

    #[Override]
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    /**
     * @throws InvalidConfigException
     */
    #[Override]
    public function getIsAvailable(): bool
    {
        if ($this->getIsRevision()) {
            return false;
        }

        if ($this->getIsDraft()) {
            return false;
        }

        if ($this->getPrimaryOwner()->getIsDraft()) {
            return false;
        }

        if ($this->getPrimaryOwner()->getStatus() != Product::STATUS_LIVE) {
            return false;
        }

        return parent::getIsAvailable();
    }

    /**
     * @return VariantCondition
     */
    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        /** @phpstan-ignore-next-line VariantCondition is still a legacy (Yii2) condition class */
        return new VariantCondition(static::class);
    }

    /**
     * Runs the custom validators that used to be declared in `defineRules()` using the
     * `[[attribute], 'methodName']` syntax. {@see VariantRules} keeps the declarative rules; these
     * are invoked automatically by {@see \CraftCms\Cms\Validation\Ruleset::after()}.
     */
    #[Override]
    public function afterValidate(?Validator $validator = null): void
    {
        if ($this->ruleset->inScenarios(VariantRules::SCENARIO_LIVE)) {
            $this->validatePrice();
        }

        $this->validateMinQtyRange();
        $this->validateMaxQtyRange();
    }

    /**
     * The base price is required for a live variant.
     */
    public function validatePrice(): void
    {
        if ($this->getBasePrice() === null) {
            $this->errors()->add('price', t('{attribute} cannot be blank.', [
                'attribute' => $this->getAttributeLabel('price'),
            ]));
        }
    }

    public function validateMinQtyRange(): void
    {
        if ($this->minQty && $this->maxQty && $this->minQty > $this->maxQty) {
            $this->errors()->add('minQty', t('Min quantity must be less than max.', category: 'commerce'));
        }
    }

    public function validateMaxQtyRange(): void
    {
        if ($this->minQty && $this->maxQty && $this->maxQty < $this->minQty) {
            $this->errors()->add('maxQty', t('Max quantity must greater than min.', category: 'commerce'));
        }
    }

    #[Override]
    public function extraFields(): array
    {
        $names = $this->nestedExtraFields();
        $names[] = 'product';

        return $names;
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        $fieldLayout = parent::getFieldLayout();

        // If we have a field layout, try to set its provider from product type
        if ($fieldLayout) {
            // TODO: migrate to app(ProductTypes::class)->getAllProductTypes() once service migrated to src/
            /** @phpstan-ignore-next-line */
            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
            $productType = collect($productTypes)->firstWhere('variantFieldLayoutId', $fieldLayout->id);

            if ($productType) {
                $fieldLayout->provider = $productType;
                return $fieldLayout;
            }
        }

        // Try to get field layout from owner's product type
        try {
            $owner = $this->getOwner();

            return $owner === null
                ? $fieldLayout
                : $owner->getType()->getVariantFieldLayout();
        } catch (InvalidConfigException) {
            // Product type was likely deleted
            return null;
        }
    }

    #[Override]
    protected function metadata(): array
    {
        $metadata = parent::metadata();

        $product = $this->getOwner();

        if ($product) {
            $metadata[t('Product', category: 'commerce')] = app(ElementHtml::class)->elementChipHtml($product, ['showActionMenu' => true]);
        }

        return $metadata;
    }

    #[\Deprecated(message: 'in 5.0.0. Use [[setOwnerId()]] instead.')]
    public function setProductId(?int $productId): void
    {
        $this->setOwnerId($productId);
    }

    /**
     * @throws InvalidConfigException
     */
    #[\Deprecated(message: 'in 5.0.0. Use [[getOwnerId()]] instead.')]
    public function getProductId(): ?int
    {
        return $this->getOwnerId();
    }

    public function setPrimaryOwner(?ElementInterface $owner): void
    {
        if (!$owner instanceof Product) {
            throw new InvalidArgumentException('Product variants can only be assigned to products.');
        }

        if ($owner->siteId) {
            $this->siteId = $owner->siteId;
        }

        $this->fieldLayoutId = $owner->getType()->variantFieldLayoutId;

        $this->nestedSetPrimaryOwner($owner);
    }

    public function setOwner(?ElementInterface $owner): void
    {
        if (!$owner instanceof Product) {
            throw new InvalidArgumentException('Product variants can only be assigned to products.');
        }

        if ($owner->siteId) {
            $this->siteId = $owner->siteId;
        }

        $this->fieldLayoutId = $owner->getType()->variantFieldLayoutId;

        $this->nestedSetOwner($owner);
    }

    /**
     * Returns the product associated with this variant.
     */
    #[\Deprecated(message: 'in 5.0.0. Use [[getOwner()]] instead.')]
    public function getProduct(): ?Product
    {
        /** @var Product|null */
        return $this->getOwner();
    }

    /**
     * Sets the product associated with this variant.
     */
    #[\Deprecated(message: 'in 5.0.0. Use [[setOwner()]] instead.')]
    public function setProduct(Product $product): void
    {
        $this->setOwner($product);
    }

    public function setProductSlug(?string $productSlug): void
    {
        $this->_productSlug = $productSlug;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getProductSlug(): ?string
    {
        if ($this->_productSlug === null) {
            $product = $this->getOwner();

            $this->_productSlug = $product?->slug ?? null;
        }

        return $this->_productSlug;
    }

    public function setProductTypeHandle(?string $productTypeHandle): void
    {
        $this->_productTypeHandle = $productTypeHandle;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getProductTypeHandle(): ?string
    {
        if ($this->_productTypeHandle === null) {
            $product = $this->getOwner();

            $this->_productTypeHandle = $product ? ($product->getType()?->handle ?? null) : null;
        }

        return $this->_productTypeHandle;
    }

    /**
     * Returns the product title and variants title together for variable products.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    #[Override]
    public function getDescription(): string
    {
        $description = $this->title;

        if ($format = $this->getOwner()->getType()->descriptionFormat) {
            if ($rendered = renderSandboxedObjectTemplate($format, $this)) {
                $description = $rendered;
            }
        }

        // If title is not set yet default to blank string
        return (string)$description;
    }

    /**
     * Updates the title based on titleFormat, or sets it to the same title as the product.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function updateTitle(Product $product): void
    {
        $type = $product->getType();

        // Use the product type's titleFormat if the title field is not shown
        if (!$type->hasVariantTitleField && $type->variantTitleFormat) {
            // Set Craft to the product's site's language, in case the title format has any static translations
            $language = $this->getSite()->getLanguage();
            $this->title = I18N::withLocale(
                $language,
                $language,
                fn() => renderSandboxedObjectTemplate($type->variantTitleFormat, $this),
            );
        }
    }

    /**
     * @throws Throwable
     */
    public function updateSku(Product $product): void
    {
        $type = $product->getType();

        // If we have a blank SKU, generate from product type’s skuFormat
        if (!$this->sku && $type->skuFormat) {
            // Set Craft to the product’s site’s language, in case the SKU format has any static translations
            $language = $this->getSite()->getLanguage();
            $this->sku = I18N::withLocale(
                $language,
                $language,
                fn() => renderSandboxedObjectTemplate($type->skuFormat, $this),
            );

            // Ensure there isn't a clash with an existing SKU when using auto formats
            if ($this->skuExists($this->getSku(), $this->id)) {
                // If there is a clash, we need to append a number to the end.
                do {
                    $seq = Sequence::next('sku::' . $this->sku);
                    $newSku = $this->sku . '-' . $seq;
                } while ($this->skuExists($newSku, $this->id));

                $this->sku = $newSku;
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

    #[Override]
    protected function cacheTags(): array
    {
        $tags = [];

        if ($primaryOwnerId = $this->getPrimaryOwnerId()) {
            $tags[] = "element::{$primaryOwnerId}";
            $tags[] = "product:{$primaryOwnerId}";
        }

        $ownerId = $this->getOwnerId();
        if ($ownerId && $ownerId !== $primaryOwnerId) {
            $tags[] = "element::{$ownerId}";
        }

        return $tags;
    }

    #[Override]
    public function canView(\CraftCms\Cms\User\Elements\User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        $product = $this->getOwner();
        if ($product === null) {
            return false;
        }

        return $product->canView($user);
    }

    #[Override]
    public function getUrl(): ?string
    {
        if ($url = parent::getUrl()) {
            return $url;
        }

        // Default URL is the product's URL with the variant ID as a query parameter
        $productUrl = $this->getOwner()?->getUrl();
        return $productUrl ? Url::urlWithParams($productUrl, ['variant' => $this->id]) : null;
    }

    /**
     * @throws InvalidConfigException
     */
    #[Override]
    public function getSnapshot(): array
    {
        $data = parent::getSnapshot();
        $data['cpEditUrl'] = $this->getCpEditUrl();

        // Default Product custom field handles
        $productFields = [];
        $productFieldsEvent = new CustomizeProductSnapshotFieldsEvent(
            product: $this->getOwner(),
            fields: $productFields,
        );

        // Allow plugins to modify Product fields to be fetched
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT, $productFieldsEvent);
        }

        // Product Attributes
        if ($product = $this->getOwner()) {
            $productAttributes = $product->attributes();

            // Remove custom fields
            if (($fieldLayout = $product->getFieldLayout()) !== null) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    $productAttributes = array_values(array_filter(
                        $productAttributes,
                        fn(string $attribute) => $attribute !== $field->handle,
                    ));
                }
            }

            // Add back the custom fields they want
            foreach ($productFieldsEvent->fields as $field) {
                $productAttributes[] = $field;
            }

            $data['product'] = $product->toArray($productAttributes, [], false);

            $productDataEvent = new CustomizeProductSnapshotDataEvent(
                product: $product,
                fieldData: $data['product'],
            );
        } else {
            $productDataEvent = new CustomizeProductSnapshotDataEvent(
                product: $this->getOwner(),
                fieldData: [],
            );
        }

        // Allow plugins to modify captured Product data
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT, $productDataEvent);
        }

        $data['product'] = $productDataEvent->fieldData;

        // Default Variant custom field handles
        $variantFields = [];
        $variantFieldsEvent = new CustomizeVariantSnapshotFieldsEvent(
            variant: $this,
            fields: $variantFields,
        );

        // Allow plugins to modify fields to be fetched
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT, $variantFieldsEvent);
        }

        $variantAttributes = $this->attributes();

        // Remove custom fields
        if (($fieldLayout = $this->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                $variantAttributes = array_values(array_filter(
                    $variantAttributes,
                    fn(string $attribute) => $attribute !== $field->handle,
                ));
            }
        }

        // Add back the custom fields they want
        foreach ($variantFieldsEvent->fields as $field) {
            $variantAttributes[] = $field;
        }

        $variantData = $this->toArray($variantAttributes, [], false);

        $variantDataEvent = new CustomizeVariantSnapshotDataEvent(
            variant: $this,
            fieldData: $variantData,
        );

        // Allow plugins to modify captured Variant data
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT, $variantDataEvent);
        }

        return array_merge($variantDataEvent->fieldData, $data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Override]
    public function hasFreeShipping(): bool
    {
        $isShippable = $this->getIsShippable(); // Same as app(Purchasables::class)->isPurchasableShippable since this has no context
        return $isShippable && $this->freeShipping;
    }

    /**
     * @return VariantQuery The newly created VariantQuery instance.
     */
    #[Override]
    public static function find(): VariantQuery
    {
        return new VariantQuery();
    }

    #[Override]
    public static function hasStatuses(): bool
    {
        return true;
    }

    #[Override]
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        switch ($handle) {
            case 'product':
                $sourceElementIds = array_filter(array_map(fn(ElementInterface $element) => $element->id, $sourceElements));

                $map = DB::table(Table::VARIANTS)
                    ->select(['id as source', 'primaryOwnerId as target'])
                    ->whereIn('id', $sourceElementIds)
                    ->get()
                    ->map(fn(object $row) => (array)$row)
                    ->all();

                return [
                    'elementType' => Product::class,
                    'map' => $map,
                    'criteria' => [
                        'status' => null,
                    ],
                ];
            case 'owner':
            case 'primaryOwner':
                return array_merge(
                    self::nestedEagerLoadingMap($sourceElements, $handle),
                    ['elementType' => Product::class],
                );
            default:
                return self::nestedEagerLoadingMap($sourceElements, $handle);
        }
    }

    /**
     * Returns a promotion category related to this element if the category is related to the product OR the variant.
     *
     * @throws InvalidConfigException
     */
    #[Override]
    public function getPromotionRelationSource(): array
    {
        return [$this->id, $this->getOwner()->id];
    }

    /**
     * @throws InvalidConfigException
     */
    #[Override]
    public function getGqlTypeName(): string
    {
        $product = $this->getOwner();

        if (!$product) {
            return 'Variant';
        }

        try {
            $productType = $product->getType();
        } catch (Exception) {
            return 'Variant';
        }

        return static::gqlTypeNameByContext($productType);
    }

    public static function gqlTypeNameByContext(mixed $context): string
    {
        return $context->handle . '_Variant';
    }

    #[Override]
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var ProductType $context */
        return ['productTypes.' . $context->uid];
    }

    #[Override]
    public function getSupportedSites(): array
    {
        $owner = $this->getOwner();

        if (!$owner) {
            return [Sites::getPrimarySite()->id];
        }

        return $owner->getSupportedSites();
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function afterSave(bool $isNew): void
    {
        $ownerId = $this->getOwnerId();

        if (!$this->propagating) {
            if (!$isNew) {
                $record = VariantRecord::query()->find($this->id);

                if (!$record) {
                    throw new Exception('Invalid variant ID: ' . $this->id);
                }
            } else {
                $record = new VariantRecord();
                $record->id = $this->id;
            }

            $record->primaryOwnerId = $this->getPrimaryOwnerId();

            if ($this->getOwner()->getIsCanonical()) {
                $record->isDefault = $this->isDefault;
            }

            // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
            $record->dateUpdated = Query::prepareDateForDb($this->dateUpdated);
            $record->dateCreated = Query::prepareDateForDb($this->dateCreated);

            $record->save();

            if ($ownerId && $this->saveOwnership) {
                if (!isset($this->sortOrder) && (!$isNew || $this->duplicateOf)) {
                    // figure out if we should proceed this way
                    // if we're dealing with an element that's being duplicated, and it has a draftId
                    // it means we're creating a draft of something
                    // if we're duplicating element via duplicate action - draftId would be empty
                    // Same as https://github.com/craftcms/cms/pull/14497/files
                    $elementId = null;
                    if ($this->duplicateOf) {
                        if ($this->draftId) {
                            $elementId = $this->duplicateOf->id;
                        }
                    } else {
                        // if we're not duplicating - use element's id
                        $elementId = $this->id;
                    }
                    if ($elementId) {
                        $this->sortOrder = DB::table(CraftTable::ELEMENTS_OWNERS)
                            ->where('elementId', $elementId)
                            ->where('ownerId', $ownerId)
                            ->value('sortOrder') ?: null;
                    }
                }

                if (!isset($this->sortOrder)) {
                    $max = DB::table(CraftTable::ELEMENTS_OWNERS . ' as eo')
                        ->join(Table::VARIANTS . ' as v', 'v.id', '=', 'eo.elementId')
                        ->where('eo.ownerId', $ownerId)
                        ->max('eo.sortOrder');
                    $this->sortOrder = $max ? $max + 1 : 1;
                }

                $ownerIds = array_unique([
                    $ownerId,
                    $this->getPrimaryOwnerId(),
                ]);

                if (!$isNew) {
                    DB::table(CraftTable::ELEMENTS_OWNERS)
                        ->where('elementId', $this->id)
                        ->whereIn('ownerId', $ownerIds)
                        ->delete();
                }

                foreach ($ownerIds as $ownerIdToSave) {
                    DB::table(CraftTable::ELEMENTS_OWNERS)->insert([
                        'elementId' => $this->id,
                        'ownerId' => $ownerIdToSave,
                        'sortOrder' => $this->sortOrder,
                    ]);
                }
            }
        }

        parent::afterSave($isNew);

        if (!$this->propagating && $this->isDefault && $ownerId && $this->duplicateOf === null) {
            // @TODO Remove this denormalized default-variant data write in Commerce 6.0; the product query now joins this data directly
            $defaultData = [
                'defaultVariantId' => $this->id,
                'defaultSku' => $this->getSkuAsText(),
                'defaultPrice' => $this->getBasePrice(),
                'defaultHeight' => $this->height,
                'defaultLength' => $this->length,
                'defaultWidth' => $this->width,
                'defaultWeight' => $this->weight,
            ];
            // Update the product that owns this variant
            DB::table(Table::PRODUCTS)->where('id', $ownerId)->update($defaultData);
            // Update any other product that references this variant as its default (split from the above to avoid deadlocks from non-deterministic lock ordering with OR-clauses)
            DB::table(Table::PRODUCTS)
                ->where('defaultVariantId', $this->id)
                ->where('id', '!=', $ownerId)
                ->update($defaultData);
        }
    }

    #[Override]
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if (in_array($handle, ['product', 'owner', 'primaryOwner'])) {
            $product = $elements[0] ?? null;
            if ($product instanceof Product) {
                if ($handle == 'primaryOwner') {
                    $this->setPrimaryOwner($product);
                } else {
                    $this->setOwner($product);
                }
            }
        } else {
            $this->nestedSetEagerLoadedElements($handle, $elements, $plan);
        }
    }

    #[Override]
    public static function hasTitles(): bool
    {
        return true;
    }

    #[Override]
    public static function isSelectable(): bool
    {
        return true;
    }

    #[Override]
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * The new validation system has no `beforeValidate(): bool` hook — the equivalent
     * pre-validation mutation point is `prepareForValidation()`.
     *
     * @throws Throwable
     * @throws InvalidConfigException
     */
    #[Override]
    public function prepareForValidation(): void
    {
        $product = $this->getOwner();

        // hold off on updating the title and SKU if we are creating the shell of the variant ready for editing
        if ($product && (!$this->getIsDraft() || !$this->ruleset->inScenarios(VariantRules::SCENARIO_ESSENTIALS))) {
            $this->updateTitle($product);
            $this->updateSku($product);
        }

        if (!$this->sku && $this->ruleset->inScenarios(VariantRules::SCENARIO_DEFAULT)) {
            $this->setSku(PurchasableHelper::tempSku());
        }
    }

    /**
     * @throws InvalidConfigException
     */
    #[Override]
    public function beforeSave(bool $isNew): bool
    {
        $product = $this->getOwner();

        // hold off on updating the title and SKU if we are creating the shell of the variant ready for editing
        if ($product && (!$this->getIsDraft() || !$this->ruleset->inScenarios(VariantRules::SCENARIO_ESSENTIALS))) {
            $this->updateTitle($product);
            $this->updateSku($product);
        }

        // Set the field layout
        $productType = $product->getType();
        $this->fieldLayoutId = $productType->variantFieldLayoutId;

        // Validate shipping category ID is available for this product type
        $availableShippingCategoryIds = collect($this->availableShippingCategories())->pluck('id')->all();

        // If the current shipping category ID is not in the available categories, set it to the default one
        if (!in_array($this->getShippingCategoryId(), $availableShippingCategoryIds)) {
            $defaultShippingCategory = app(ShippingCategories::class)->getDefaultShippingCategory($this->getStoreId());
            $this->setShippingCategoryId($defaultShippingCategory->id);
        }

        return parent::beforeSave($isNew);
    }

    #[Override]
    public function afterAssignedId(): void
    {
        if (ElementHelper::isDraftOrRevision($this)) {
            return;
        }

        $product = $this->getOwner();

        if ($product) {
            $this->updateTitle($product);
        }
    }

    #[Override]
    public function beforeRestore(): bool
    {
        if (!parent::beforeRestore()) {
            return false;
        }

        // Check to see if any other purchasable has the same SKU and update this one before restore
        $found = DB::table(Table::PURCHASABLES . ' as p')
            ->leftJoin(CraftTable::ELEMENTS . ' as e', 'p.id', '=', 'e.id')
            ->whereNull('e.dateDeleted')
            ->where('p.sku', $this->getSku())
            ->where('e.id', '!=', $this->getId())
            ->count();

        if ($found) {
            // Set new SKU in memory
            $this->sku = $this->getSku() . '-1';

            // Update purchasable table with new SKU
            DB::table(Table::PURCHASABLES)
                ->where('id', $this->getId())
                ->update(['sku' => $this->sku]);
        }

        return true;
    }

    /**
     * @throws InvalidConfigException
     */
    #[Override]
    public function getSearchKeywords(string $attribute): string
    {
        if ($attribute == 'productTitle') {
            return $this->getOwner()->title ?? '';
        }

        return parent::getSearchKeywords($attribute);
    }

    /**
     * @return ShippingCategory[]
     */
    #[Override]
    protected function availableShippingCategories(): array
    {
        $allAvailableShippingCategories = parent::availableShippingCategories();

        $productTypeId = $this->getPrimaryOwner()?->getType()->id;

        if (!$productTypeId) {
            return [app(ShippingCategories::class)->getDefaultShippingCategory($this->storeId)];
        }

        // Limit to only those for this product type
        $categoryIds = collect(app(ShippingCategories::class)->getShippingCategoriesByProductTypeId($productTypeId))->pluck('id')->all();
        $available = collect($allAvailableShippingCategories)->filter(fn(ShippingCategory $category) => in_array($category->id, $categoryIds));

        if ($available->isEmpty()) {
            return [app(ShippingCategories::class)->getDefaultShippingCategory($this->storeId)];
        }

        return $available->values()->all();
    }

    /**
     * @return TaxCategory[]
     */
    #[Override]
    protected function availableTaxCategories(): array
    {
        $allAvailableTaxCategories = parent::availableTaxCategories();

        $productTypeId = $this->getPrimaryOwner()?->getType()->id;

        if (!$productTypeId) {
            return [app(TaxCategories::class)->getDefaultTaxCategory()];
        }

        // Limit to only those for this product type
        $categoryIds = collect(app(TaxCategories::class)->getTaxCategoriesByProductTypeId($productTypeId))->pluck('id')->all();
        $available = collect($allAvailableTaxCategories)->filter(fn(TaxCategory $category) => in_array($category->id, $categoryIds));

        if ($available->isEmpty()) {
            return [app(TaxCategories::class)->getDefaultTaxCategory()];
        }

        return $available->values()->all();
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        $sources = Product::sources($context);

        // Ensure we don't inherit any product structure things from products.
        foreach ($sources as $key => $source) {
            $sources[$key]['defaultSort'] = ['postDate', 'desc'];
            foreach (['structureId', 'structureEditable'] as $unsetKey) {
                if (isset($sources[$key][$unsetKey])) {
                    unset($sources[$key][$unsetKey]);
                }
            }
        }

        return $sources;
    }

    #[Override]
    protected static function defineActions(string $source): array
    {
        $actions = parent::defineActions($source);

        // Restore
        $actions[] = ElementActions::createAction([
            'type' => Restore::class,
            'successMessage' => t('Variants restored.', category: 'commerce'),
            'partialSuccessMessage' => t('Some variants restored.', category: 'commerce'),
            'failMessage' => t('Variants not restored.', category: 'commerce'),
        ], static::class);

        if ($source === '__IMP__') {
            $actions[] = ['type' => SetDefaultVariant::class];
        }

        $actions[] = ['type' => Copy::class];

        return $actions;
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'product' => t('Product', category: 'commerce'),
            'isDefault' => t('Default', category: 'commerce'),
            'promotable' => t('Promotable', category: 'commerce'),
        ]);
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        // Only add product as a `product` if we are viewing an implicit table
        $extras = ['isDefault'];

        if ($source !== '__IMP__') {
            $extras[] = 'product';
        }

        return [...parent::defineDefaultTableAttributes($source), ...$extras];
    }

    #[Override]
    protected static function defineSearchableAttributes(): array
    {
        return [...parent::defineSearchableAttributes(), ...['productTitle']];
    }

    #[Override]
    protected static function defineCardAttributes(): array
    {
        return array_merge(parent::defineCardAttributes(), [
            'product' => [
                'label' => t('Product', category: 'commerce'),
            ],
            'isDefault' => [
                'label' => t('Default', category: 'commerce'),
            ],
            'promotable' => [
                'label' => t('Promotable', category: 'commerce'),
            ],
        ]);
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        if ($attribute === 'product') {
            $product = $this->getOwner();
            if (!$product) {
                return '';
            }

            return sprintf('<span class="status %s"></span> %s', $product->getStatus(), Html::encode($product->title));
        }

        if ($attribute === 'isDefault') {
            if ($this->isDefault) {
                $isDefault = Html::tag('span', '', [
                    'class' => 'checkbox-icon',
                    'role' => 'img',
                    'title' => t('Enabled'),
                    'aria' => [
                        'label' => t('Enabled'),
                    ],
                ]);
                return $isDefault . Html::tag('span', ' ' . t('Default', category: 'commerce'), [
                    'class' => 'card-only-label',
                    'style' => 'display:none;',
                ]) . Html::tag('style', '.card-content .card-only-label { display: inline !important; }');
            }
        }

        if ($attribute === 'promotable') {
            if ($this->promotable) {
                $promotable = Html::tag('span', '', [
                    'class' => 'checkbox-icon',
                    'role' => 'img',
                    'title' => t('Enabled'),
                    'aria' => [
                        'label' => t('Enabled'),
                    ],
                ]);
                return $promotable . Html::tag('span', ' ' . t('Promotable', category: 'commerce'), [
                    'class' => 'card-only-label',
                    'style' => 'display:none;',
                ]) . Html::tag('style', '.card-content .card-only-label { display: inline !important; }');
            }
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * Variants are always owned by products.
     */
    protected function ownerType(): ?string
    {
        return Product::class;
    }
}
