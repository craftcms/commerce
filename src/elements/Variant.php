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
use craft\base\NestedElementInterface;
use craft\base\NestedElementTrait;
use craft\commerce\base\Purchasable;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\db\Table;
use craft\commerce\elements\actions\SetDefaultVariant;
use craft\commerce\elements\conditions\variants\VariantCondition;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\events\CustomizeProductSnapshotDataEvent;
use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;
use craft\commerce\events\CustomizeVariantSnapshotDataEvent;
use craft\commerce\events\CustomizeVariantSnapshotFieldsEvent;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\ProductType;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Variant as VariantRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\EagerLoadPlan;
use craft\elements\User;
use craft\gql\types\DateTime;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Variant model.
 *
 * @property string $eagerLoadedElements some eager-loaded elements on a given handle
 * @property bool $onSale
 * @property Sale[] $sales sales models which are currently affecting the salePrice of this purchasable
 * @property string $priceAsCurrency
 * @property DateTime|null $dateUpdated
 * @property DateTime|null $dateCreated
 * @property Product|null $owner
 * @property Product|null $primaryOwner
 * @property-read string[] $cacheTags
 * @property-read string $gqlTypeName
 * @property-read string $skuAsText
 * @property string $salePriceAsCurrency
 * @method Product|null getOwner()
 * @method Product|null getPrimaryOwner()
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variant extends Purchasable implements NestedElementInterface
{
    use NestedElementTrait {
        eagerLoadingMap as traitEagerLoadingMap;
        setPrimaryOwner as traitSetPrimaryOwner;
        setOwner as traitSetOwner;
        setEagerLoadedElements as traitSetEagerLoadedElements;
    }

    /**
     * @event craft\commerce\events\CustomizeVariantSnapshotFieldsEvent The event that is triggered before a variant’s field data is captured, which makes it possible to customize which fields are included in the snapshot. Custom fields are not included by default.
     *
     * This example adds every custom field to the variant snapshot:
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\events\CustomizeVariantSnapshotFieldsEvent;
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
    public const EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT = 'beforeCaptureVariantSnapshot';

    /**
     * @event craft\commerce\events\CustomizeVariantSnapshotDataEvent The event that is triggered after a variant’s field data is captured. This makes it possible to customize, extend, or redact the data to be persisted on the variant instance.
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\events\CustomizeVariantSnapshotDataEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     Variant::class,
     *     Variant::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT,
     *     function(CustomizeVariantSnapshotDataEvent $event) {
     *         // @var Variant $variant
     *         $variant = $event->variant;
     *         // @var array|null $fields
     *         $fields = $event->fields;
     *
     *         // Modify or redact captured `$data`
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT = 'afterCaptureVariantSnapshot';

    /**
     * @event craft\commerce\events\CustomizeProductSnapshotFieldsEvent The event that is triggered before a product’s field data is captured. This makes it possible to customize which fields are included in the snapshot. Custom fields are not included by default.
     *
     * This example adds every custom field to the product snapshot:
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\elements\Product;
     * use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     Variant::class,
     *     Variant::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT,
     *     function(CustomizeProductSnapshotFieldsEvent $event) {
     *         // @var Product $product
     *         $product = $event->product;
     *         // @var array|null $fields
     *         $fields = $event->fields;
     *
     *         // Add every custom field to the snapshot
     *         if (($fieldLayout = $product->getFieldLayout()) !== null) {
     *             foreach ($fieldLayout->getFields() as $field) {
     *                 $fields[] = $field->handle;
     *             }
     *         }
     *
     *         $event->fields = $fields;
     *     }
     * );
     * ```
     *
     * ::: warning
     * Add with care! A huge amount of custom fields/data will increase your database size.
     * :::
     */
    public const EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT = 'beforeCaptureProductSnapshot';

    /**
     * @event craft\commerce\events\CustomizeProductSnapshotDataEvent The event that is triggered after a product’s field data is captured, which can be used to customize, extend, or redact the data to be persisted on the product instance.
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\elements\Product;
     * use craft\commerce\events\CustomizeProductSnapshotDataEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     Variant::class,
     *     Variant::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT,
     *     function(CustomizeProductSnapshotDataEvent $event) {
     *         // @var Product $product
     *         $product = $event->product;
     *         // @var array $data
     *         $data = $event->fieldData;
     *
     *         // Modify or redact captured `$data`
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT = 'afterCaptureProductSnapshot';

    /**
     * @var bool $isDefault
     */
    public bool $isDefault = false;

    /**
     * @var int|null $sortOrder
     */
    public ?int $sortOrder = null;

    /**
     * @var bool Whether the variant was deleted along with its product
     * @see beforeDelete()
     */
    public bool $deletedWithProduct = false;

    /**
     * @var string|null
     * @see getProductSlug()
     * @see setProductSlug()
     */
    private ?string $_productSlug = null;

    /**
     * @var string|null
     * @see getProductTypeHandle()
     * @see setProductTypeHandle()
     */
    private ?string $_productTypeHandle = null;

    /**
     * @throws InvalidConfigException
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'currencyAttributes' => $this->currencyAttributes(),
        ];

        return $behaviors;
    }

    public function safeAttributes()
    {
        $attributes = parent::safeAttributes();
        $attributes[] = 'productId';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Product Variant');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('commerce', 'product variant');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce', 'Product Variants');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('commerce', 'product variants');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'variant';
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
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

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        if (parent::canDelete($user)) {
            return true;
        }

        return $this->canSave($user);
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function getIsAvailable(): bool
    {
        if ($this->getPrimaryOwner()->getIsDraft()) {
            return false;
        }

        if ($this->getPrimaryOwner()->status != Product::STATUS_LIVE) {
            return false;
        }

        return parent::getIsAvailable();
    }

    /**
     * @inheritdoc
     * @return VariantCondition
     * @throws InvalidConfigException
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(VariantCondition::class, [static::class]);
    }

    /**
     * @return void
     * @noinspection PhpUnused
     */
    public function validateMinQtyRange()
    {
        if ($this->minQty && $this->maxQty && $this->minQty > $this->maxQty) {
            $this->addError('minQty', Craft::t('commerce', 'Min quantity must be less than max.'));
        }
    }

    /**
     * @return void
     * @noinspection PhpUnused
     */
    public function validateMaxQtyRange()
    {
        if ($this->minQty && $this->maxQty && $this->maxQty < $this->minQty) {
            $this->addError('maxQty', Craft::t('commerce', 'Max quantity must greater than min.'));
        }
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'product';
        return $names;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     * @throws InvalidConfigException
     * @throws InvalidConfigException
     */
    public function getFieldLayout(): ?FieldLayout
    {
        $fieldLayout = parent::getFieldLayout();

        if (!$fieldLayout && $this->getOwnerId()) {
            $fieldLayout = $this->getOwner()->getType()->getVariantFieldLayout();
            $this->fieldLayoutId = $fieldLayout->id;
        }

        return $fieldLayout;
    }

    /**
     * @param int|null $productId
     * @return void
     * @since 5.0.0
     * @deprecated in 5.0.0. Use [[setOwnerId()]] instead.
     */
    public function setProductId(?int $productId)
    {
        $this->setOwnerId($productId);
    }

    /**
     * @return int|null
     * @throws InvalidConfigException
     * @deprecated in 5.0.0. Use [[getOwnerId()]] instead.
     * @since 5.0.0
     */
    public function getProductId(): ?int
    {
        return $this->getOwnerId();
    }

    /**
     * @inheritdoc
     */
    public function setPrimaryOwner(?ElementInterface $owner): void
    {
        if (!$owner instanceof Product) {
            throw new InvalidArgumentException('Product variants can only be assigned to products.');
        }

        if ($owner->siteId) {
            $this->siteId = $owner->siteId;
        }

        $this->fieldLayoutId = $owner->getType()->variantFieldLayoutId;

        $this->traitSetPrimaryOwner($owner);
    }

    /**
     * @inheritdoc
     */
    public function setOwner(?ElementInterface $owner): void
    {
        if (!$owner instanceof Product) {
            throw new InvalidArgumentException('Product variants can only be assigned to products.');
        }

        if ($owner->siteId) {
            $this->siteId = $owner->siteId;
        }

        $this->fieldLayoutId = $owner->getType()->variantFieldLayoutId;

        $this->traitSetOwner($owner);
    }

    /**
     * Returns the product associated with this variant.
     *
     * @return Product|null The product associated with this variant, or null if it isn’t known
     * @deprecated in 5.0.0. Use [[getOwner()]] instead.
     */
    public function getProduct(): ?Product
    {
        /** @var Product|null */
        return $this->getOwner();
    }

    /**
     * Sets the product associated with this variant.
     *
     * @param Product $product The product associated with this variant
     * @deprecated in 5.0.0. Use [[setOwner()]] instead.
     */
    public function setProduct(Product $product): void
    {
        $this->setOwner($product);
    }

    /**
     * @param string|null $productSlug
     * @return void
     * @since 5.0.0
     */
    public function setProductSlug(?string $productSlug): void
    {
        $this->_productSlug = $productSlug;
    }

    /**
     * @return string|null
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getProductSlug(): ?string
    {
        if ($this->_productSlug === null) {
            $product = $this->getOwner();

            $this->_productSlug = $product?->slug ?? null;
        }

        return $this->_productSlug;
    }

    /**
     * @param string|null $productTypeHandle
     * @return void
     * @since 5.0.0
     */
    public function setProductTypeHandle(?string $productTypeHandle): void
    {
        $this->_productTypeHandle = $productTypeHandle;
    }

    /**
     * @return string|null
     * @throws InvalidConfigException
     * @since 5.0.0
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
    public function getDescription(): string
    {
        $description = $this->title;

        if ($format = $this->getOwner()->getType()->descriptionFormat) {
            if ($rendered = Craft::$app->getView()->renderObjectTemplate($format, $this)) {
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
     * @see \craft\elements\Entry::updateTitle
     */
    public function updateTitle(Product $product): void
    {
        $type = $product->getType();
        // Use the product type's titleFormat if the title field is not shown
        if (!$type->hasVariantTitleField && $type->variantTitleFormat) {
            // Make sure that the locale has been loaded in case the title format has any Date/Time fields
            Craft::$app->getLocale();
            // Set Craft to the product's site's language, in case the title format has any static translations
            $language = Craft::$app->language;
            Craft::$app->language = $this->getSite()->language;
            $this->title = Craft::$app->getView()->renderObjectTemplate($type->variantTitleFormat, $this);
            Craft::$app->language = $language;
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
            // Make sure that the locale has been loaded in case the title format has any Date/Time fields
            Craft::$app->getLocale();
            // Set Craft to the product’s site’s language, in case the title format has any static translations
            $language = Craft::$app->language;
            Craft::$app->language = $this->getSite()->language;
            $this->sku = Craft::$app->getView()->renderObjectTemplate($type->skuFormat, $this);
            Craft::$app->language = $language;
        }
    }

    /**
     * @inheritdoc
     */
    protected function cacheTags(): array
    {
        return [
            "product:$this->primaryOwnerId",
        ];
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
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

    /**
     * @inheritdoc
     */
    public function getUrl(): ?string
    {
        $productUrl = $this->getOwner()?->getUrl();
        return $productUrl ? UrlHelper::urlWithParams($productUrl, ['variant' => $this->id]) : null;
    }

    /**
     *
     * @throws InvalidConfigException
     */
    public function getSnapshot(): array
    {
        $data = parent::getSnapshot();
        $data['cpEditUrl'] = $this->getCpEditUrl();

        // Default Product custom field handles
        $productFields = [];
        $productFieldsEvent = new CustomizeProductSnapshotFieldsEvent([
            'product' => $this->getOwner(),
            'fields' => $productFields,
        ]);

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
                    ArrayHelper::removeValue($productAttributes, $field->handle);
                }
            }

            // Add back the custom fields they want
            foreach ($productFieldsEvent->fields as $field) {
                $productAttributes[] = $field;
            }

            $data['product'] = $this->getOwner()->toArray($productAttributes, [], false);

            $productDataEvent = new CustomizeProductSnapshotDataEvent([
                'product' => $this->getOwner(),
                'fieldData' => $data['product'],
            ]);
        } else {
            $productDataEvent = new CustomizeProductSnapshotDataEvent([
                'product' => $this->getOwner(),
                'fieldData' => [],
            ]);
        }

        // Allow plugins to modify captured Product data
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT, $productDataEvent);
        }

        $data['product'] = $productDataEvent->fieldData;

        // Default Variant custom field handles
        $variantFields = [];
        $variantFieldsEvent = new CustomizeVariantSnapshotFieldsEvent([
            'variant' => $this,
            'fields' => $variantFields,
        ]);

        // Allow plugins to modify fields to be fetched
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT, $variantFieldsEvent);
        }

        $variantAttributes = $this->attributes();

        // Remove custom fields
        if (($fieldLayout = $this->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                ArrayHelper::removeValue($variantAttributes, $field->handle);
            }
        }

        // Add back the custom fields they want
        foreach ($variantFieldsEvent->fields as $field) {
            $variantAttributes[] = $field;
        }

        $variantData = $this->toArray($variantAttributes, [], false);

        $variantDataEvent = new CustomizeVariantSnapshotDataEvent([
            'variant' => $this,
            'fieldData' => $variantData,
        ]);

        // Allow plugins to modify captured Variant data
        if ($this->hasEventHandlers(self::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT)) {
            $this->trigger(self::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT, $variantDataEvent);
        }

        return array_merge($variantDataEvent->fieldData, $data);
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function hasFreeShipping(): bool
    {
        $isShippable = $this->getIsShippable(); // Same as Plugin::getInstance()->getPurchasables()->isPurchasableShippable since this has no context
        return $isShippable && $this->freeShipping;
    }

    /**
     * @inheritdoc
     * @return VariantQuery The newly created [[VariantQuery]] instance.
     */
    public static function find(): VariantQuery
    {
        return new VariantQuery(static::class);
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
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle == 'product') {
            // Get the source element IDs
            $sourceElementIds = [];

            foreach ($sourceElements as $sourceElement) {
                $sourceElementIds[] = $sourceElement->id;
            }

            $map = (new Query())
                ->select('id as source, primaryOwnerId as target')
                ->from(Table::VARIANTS)
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return [
                'elementType' => Product::class,
                'map' => $map,
                'criteria' => [
                    'status' => null,
                ],
            ];
        }

        return self::traitEagerLoadingMap($sourceElements, $handle);
    }

    /**
     * Returns a promotion category related to this element if the category is related to the product OR the variant.
     *
     * @throws InvalidConfigException
     */
    public function getPromotionRelationSource(): array
    {
        return [$this->id, $this->getOwner()->id];
    }

    /**
     * @throws InvalidConfigException
     * @since 3.1
     */
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

    /**
     * @param mixed $context
     * @return string
     * @since 3.1
     */
    public static function gqlTypeNameByContext(mixed $context): string
    {
        return $context->handle . '_Variant';
    }

    /**
     * @param mixed $context
     * @return array
     * @since 3.1
     */
    public static function gqlScopesByContext(mixed $context): array
    {
        /** @var ProductType $context */
        return ['productTypes.' . $context->uid];
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if (!$isNew) {
                $record = VariantRecord::findOne($this->id);

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
            $record->dateUpdated = $this->dateUpdated;
            $record->dateCreated = $this->dateCreated;

            $record->save(false);

            $ownerId = $this->getOwnerId();

            if ($this->isDefault && $ownerId) {
                $defaultData = [
                    'defaultVariantId' => $this->id,
                    'defaultSku' => $this->sku,
                    'defaultPrice' => $this->getBasePrice(),
                    'defaultHeight' => $this->height,
                    'defaultLength' => $this->length,
                    'defaultWidth' => $this->width,
                    'defaultWeight' => $this->weight,
                ];
                DB::update(Table::PRODUCTS, $defaultData, [
                    // Update the default variant data for the product and any other product that use this variant as their default
                    'or',
                    ['id' => $ownerId],
                    ['defaultVariantId' => $this->id],
                ]);
            }

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
                        $this->sortOrder = (new Query())
                            ->select('sortOrder')
                            ->from(CraftTable::ELEMENTS_OWNERS)
                            ->where([
                                'elementId' => $elementId,
                                'ownerId' => $ownerId,
                            ])
                            ->scalar() ?: null;
                    }
                }
                if (!isset($this->sortOrder)) {
                    $max = (new Query())
                        ->from(['eo' => CraftTable::ELEMENTS_OWNERS])
                        ->innerJoin(['v' => Table::VARIANTS], '[[v.id]] = [[eo.elementId]]')
                        ->where([
                            'eo.ownerId' => $ownerId,
                        ])
                        ->max('[[eo.sortOrder]]');
                    $this->sortOrder = $max ? $max + 1 : 1;
                }
                if ($isNew) {
                    Db::insert(CraftTable::ELEMENTS_OWNERS, [
                        'elementId' => $this->id,
                        'ownerId' => $ownerId,
                        'sortOrder' => $this->sortOrder,
                    ]);
                } else {
                    Db::update(CraftTable::ELEMENTS_OWNERS, [
                        'sortOrder' => $this->sortOrder,
                    ], [
                        'elementId' => $this->id,
                        'ownerId' => $ownerId,
                    ]);
                }
            }
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function setEagerLoadedElements(string $handle, array $elements, EagerLoadPlan $plan): void
    {
        if ($handle == 'product') {
            $product = $elements[0] ?? null;
            if ($product instanceof Product) {
                $this->setOwner($product);
            }
        } else {
            $this->traitSetEagerLoadedElements($handle, $elements, $plan);
        }
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
    public static function isSelectable(): bool
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
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function beforeValidate(): bool
    {
        $product = $this->getOwner();

        $this->updateTitle($product);
        $this->updateSku($product);

        if ($this->getScenario() === self::SCENARIO_DEFAULT) {
            if (!$this->sku) {
                $this->setSku(PurchasableHelper::tempSku());
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @throws InvalidConfigException
     */
    public function beforeSave(bool $isNew): bool
    {
        $product = $this->getOwner();

        $this->updateTitle($product);
        $this->updateSku($product);

        // Set the field layout
        $productType = $product->getType();
        $this->fieldLayoutId = $productType->variantFieldLayoutId;

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     * @throws \yii\db\Exception
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        Craft::$app->getDb()->createCommand()
            ->update(Table::VARIANTS, [
                'deletedWithProduct' => $this->deletedWithProduct,
            ], ['id' => $this->id], [], false)
            ->execute();

        return true;
    }

    /**
     * @inheritdoc
     * @throws \yii\db\Exception
     */
    public function beforeRestore(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Check to see if any other purchasable has the same SKU and update this one before restore
        $found = (new Query())->select(['[[p.sku]]', '[[e.id]]'])
            ->from(Table::PURCHASABLES . ' p')
            ->leftJoin(CraftTable::ELEMENTS . ' e', '[[p.id]]=[[e.id]]')
            ->where(['[[e.dateDeleted]]' => null, '[[p.sku]]' => $this->getSku()])
            ->andWhere(['not', ['[[e.id]]' => $this->getId()]])
            ->count();

        if ($found) {
            // Set new SKU in memory
            $this->sku = $this->getSku() . '-1';

            // Update variant table with new SKU
            Craft::$app->getDb()->createCommand()->update(Table::VARIANTS,
                ['sku' => $this->sku],
                ['id' => $this->getId()]
            )->execute();

            if ($this->isDefault) {
                Craft::$app->getDb()->createCommand()->update(Table::PRODUCTS,
                    ['defaultSku' => $this->sku],
                    ['id' => $this->primaryOwnerId]
                )->execute();
            }

            // Update purchasable table with new SKU
            Craft::$app->getDb()->createCommand()->update(Table::PURCHASABLES,
                ['sku' => $this->sku],
                ['id' => $this->getId()]
            )->execute();
        }

        return true;
    }

    /**
     * @throws \yii\db\Exception
     */
    public function afterRestore(): void
    {
        // Once restored, we no longer track if it was deleted with variant or not
        $this->deletedWithProduct = false;
        Craft::$app->getDb()->createCommand()->update(Table::VARIANTS,
            ['deletedWithProduct' => false],
            ['id' => $this->getId()]
        )->execute();

        parent::afterRestore();
    }

    /**
     * @throws InvalidConfigException
     * @since 2.2
     */
    public function getSearchKeywords(string $attribute): string
    {
        if ($attribute == 'productTitle') {
            return $this->getOwner()->title;
        }

        return parent::getSearchKeywords($attribute);
    }

    public function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['sku'], 'string', 'max' => 255],
            [['sku', 'price'], 'required', 'on' => self::SCENARIO_LIVE],
            [['price', 'weight', 'width', 'height', 'length'], 'number'],
            // maxQty must be greater than minQty and minQty must be less than maxQty
            [['minQty'], 'validateMinQtyRange', 'skipOnEmpty' => true],
            [['maxQty'], 'validateMaxQtyRange', 'skipOnEmpty' => true],
            [['stock', 'fieldId', 'ownerId', 'primaryOwnerId'], 'number'],
            [['ownerId', 'primaryOwnerId', 'isDefault'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function availableShippingCategories(): array
    {
        $productTypeId = $this->getPrimaryOwner()?->getType()->id;
        if ($productTypeId) {
            return Plugin::getInstance()->getShippingCategories()->getShippingCategoriesByProductTypeId($productTypeId);
        }

        return parent::availableShippingCategories();
    }

    /**
     * @inheritdoc
     */
    protected function availableTaxCategories(): array
    {
        $productTypeId = $this->getPrimaryOwner()?->getType()->id;
        if ($productTypeId) {
            return Plugin::getInstance()->getTaxCategories()->getTaxCategoriesByProductTypeId($productTypeId);
        }

        return parent::availableTaxCategories();
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        return Product::sources($context);
    }

    protected static function defineActions(string $source): array
    {
        return [...parent::defineActions($source), ...[
            ['type' => SetDefaultVariant::class],
        ]];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'product' => Craft::t('commerce', 'Product'),
            'isDefault' => Craft::t('commerce', 'Default'),
            'promotable' => Craft::t('commerce', 'Promotable'),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        // Only add product as a `product` if we are viewing an implicit table
        if ($source !== "__IMP__") {
            $extras[] = 'product';
        }
        $extras = ['isDefault'];

        return [...parent::defineDefaultTableAttributes($source), ...$extras];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [...parent::defineSearchableAttributes(), ...['productTitle']];
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        if ($attribute === 'product') {
            $product = $this->getOwner();
            if (!$product) {
                return '';
            }

            return sprintf('<span class="status %s"></span> %s', $product->getStatus(), Html::encode($product->title));
        }

        return parent::attributeHtml($attribute);
    }
}
