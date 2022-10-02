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
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\db\Table;
use craft\commerce\elements\actions\CreateDiscount;
use craft\commerce\elements\actions\CreateSale;
use craft\commerce\elements\conditions\products\ProductCondition;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\helpers\Product as ProductHelper;
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
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\DateTimeValidator;
use DateTime;
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
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Product extends Element
{
    public const STATUS_LIVE = 'live';
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';

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
     * @var int|null Tax category ID
     */
    public ?int $taxCategoryId = null;

    /**
     * @var int|null Shipping category ID
     */
    public ?int $shippingCategoryId = null;

    /**
     * @var bool Whether the product is promotable
     */
    public bool $promotable = false;

    /**
     * @var bool Whether the product has free shipping
     */
    public bool $freeShipping = false;

    /**
     * @var bool Is this product available to be purchased
     */
    public bool $availableForPurchase = true;

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
     */
    public ?float $defaultPrice = null;

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
     * @var Variant[]|null This product’s variants
     */
    private ?array $_variants = null;

    /**
     * @var Variant[]|null This product’s enabled variants
     */
    private ?array $_enabledVariants = null;

    /**
     * @var Variant|null This product's cheapest variant
     */
    private ?Variant $_cheapestVariant = null;

    /**
     * @var Variant|null This product's cheapest enabled variant
     */
    private ?Variant $_cheapestEnabledVariant = null;

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

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'defaultCurrency' => Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso(),
            'currencyAttributes' => $this->currencyAttributes(),
        ];

        return $behaviors;
    }

    /**
     * @return array
     */
    public function currencyAttributes(): array
    {
        return [
            'defaultPrice',
        ];
    }

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
     * @return ProductCondition
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ProductCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
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
     * @return ProductQuery The newly created [[ProductQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new ProductQuery(static::class);
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
        return $this->canDelete($user);
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
    public function getCrumbs(): array
    {
        $type = $this->getType();

        return [
            [
                'label' => Craft::t('commerce', 'Products'),
                'url' => 'commerce/products',
            ],
            [
                'label' => Craft::t('site', $type->name),
                'url' => "commerce/products/$type->name",
            ],
        ];
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
     * Returns the tax category.
     *
     * @throws InvalidConfigException
     */
    public function getTaxCategory(): TaxCategory
    {
        $taxCategory = null;

        if ($this->taxCategoryId) {
            $taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        if (!$taxCategory) {
            // Use default as we must have a category ID
            $taxCategory = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory();
            $this->taxCategoryId = $taxCategory->id;
        }

        return $taxCategory;
    }

    /**
     * Returns the shipping category.
     *
     * @throws InvalidConfigException
     */
    public function getShippingCategory(): ShippingCategory
    {
        $shippingCategory = null;
        if ($this->shippingCategoryId) {
            $shippingCategory = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
        }

        if (!$shippingCategory) {
            // Use default as we must have a category ID
            $shippingCategory = Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory();
            $this->shippingCategoryId = $shippingCategory->id;
        }

        return $shippingCategory;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        $productType = $this->getType();

        // The slug *might* not be set if this is a Draft and they've deleted it for whatever reason
        return UrlHelper::cpUrl('commerce/products/' . $productType->handle . '/' . $this->id . ($this->slug ? '-' . $this->slug : ''));
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
        $variants = $this->getVariants($includeDisabled);

        $defaultVariant = ArrayHelper::firstWhere($variants, 'isDefault', true, false);

        return $defaultVariant ?: ArrayHelper::firstValue($variants);
    }

    /**
     * Return the cheapest variant.
     *
     * @throws InvalidConfigException
     * @noinspection PhpUnused
     */
    public function getCheapestVariant(bool $includeDisabled = false): ?Variant
    {
        if ($includeDisabled && $this->_cheapestVariant) {
            return $this->_cheapestVariant;
        }

        if (!$includeDisabled && $this->_cheapestEnabledVariant) {
            return $this->_cheapestEnabledVariant;
        }

        foreach ($this->getVariants(true) as $variant) {
            if (
                !$this->_cheapestVariant
                || $variant->getSalePrice() < $this->_cheapestVariant->getSalePrice()
            ) {
                $this->_cheapestVariant = $variant;
            }

            if (
                $variant->enabled
                &&
                (
                    !$this->_cheapestEnabledVariant
                    || $variant->getSalePrice() < $this->_cheapestEnabledVariant->getSalePrice()
                )
            ) {
                $this->_cheapestEnabledVariant = $variant;
            }
        }

        return $includeDisabled ? $this->_cheapestVariant : $this->_cheapestEnabledVariant;
    }

    /**
     * Returns an array of the product's variants.
     *
     * @return Variant[]
     * @throws InvalidConfigException
     */
    public function getVariants(bool $includeDisabled = false): array
    {
        // If we are currently duplicating a product, we don't want to have any variants.
        // We will be duplicating variants and adding them back.
        if ($this->duplicateOf) {
            $this->_variants = [];
            $this->_enabledVariants = [];
            return $this->_variants;
        }

        if (!isset($this->_variants) && $this->id) {
            $variants = Plugin::getInstance()->getVariants()->getAllVariantsByProductId($this->id, $this->siteId);

            if (!$this->getType()->hasVariants && !empty($variants)) {
                $variant = array_shift($variants);
                $variant->isDefault = true;
                $variants = [$variant];
            }

            $this->setVariants($variants);
        }

        if (empty($this->_variants)) {
            $variant = new Variant();
            $variant->isDefault = true;
            $this->setVariants([$variant]);
        }

        return $includeDisabled ? $this->_variants : $this->_enabledVariants;
    }

    /**
     * Sets the variants on the product. Accepts an array of variant data keyed by variant ID or the string 'new'.
     *
     * @param array|Variant[] $variants
     * @throws InvalidConfigException
     */
    public function setVariants(array $variants): void
    {
        $this->_variants = [];
        $this->_enabledVariants = [];

        $count = 1;
        foreach ($variants as $key => $variant) {
            if (!$variant instanceof Variant) {
                $variant = ProductHelper::populateProductVariantModel($this, $variant, $key);
            }
            $variant->sortOrder = $count++;
            $variant->setProduct($this);

            $this->_variants[] = $variant;
            if ($variant->enabled) {
                $this->_enabledVariants[] = $variant;
            }
        }
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
            if (!$variant->hasUnlimitedStock) {
                $stock += $variant->stock;
            }
        }

        return $stock;
    }

    /**
     * Returns whether at least one variant has unlimited stock.
     *
     * @throws InvalidConfigException
     */
    public function getHasUnlimitedStock(bool $includeDisabled = false): bool
    {
        foreach ($this->getVariants($includeDisabled) as $variant) {
            if ($variant->hasUnlimitedStock) {
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
    public function setEagerLoadedElements(string $handle, array $elements): void
    {
        if ($handle == 'variants') {
            /** @var Variant[] $elements */
            $this->setVariants($elements);
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle == 'variants') {
            $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

            $map = (new Query())
                ->select('productId as source, id as target')
                ->from([Table::VARIANTS])
                ->where(['productId' => $sourceElementIds])
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
     */
    public function getSidebarHtml(bool $static): string
    {
        $html = [];

        // General Meta fields
        $topMetaHtml = Craft::$app->getView()->renderObjectTemplate('{% import "commerce/products/_fields" as productFields %}{{ productFields.generalMetaFields(product) }}', null, ['product' => $this], Craft::$app->getView()::TEMPLATE_MODE_CP);

        // Enabled field
        $topMetaHtml .= Cp::lightswitchFieldHtml([
            'label' => Craft::t('commerce', 'Enabled'),
            'id' => 'enabled',
            'name' => 'enabled',
            'on' => $this->enabled,
        ]);

        // Multi site enabled
        if (Craft::$app->getIsMultiSite()) {
            $topMetaHtml .= Cp::lightswitchFieldHtml([
                'label' => Craft::t('commerce', 'Enabled for site'),
                'id' => 'enabledForSite',
                'name' => 'enabledForSite',
                'on' => $this->enabledForSite,
            ]);
        }

        $html[] = Html::tag('div', $topMetaHtml, ['class' => 'meta']);

        $html[] = Html::tag('div', Craft::$app->getView()->renderObjectTemplate(
            '{% import "commerce/products/_fields" as productFields %}{{ productFields.behavioralMetaFields(product) }}',
            null,
            ['product' => $this],
            Craft::$app->getView()::TEMPLATE_MODE_CP
        ), ['class' => 'meta']);

        $html[] = Craft::$app->getView()->renderObjectTemplate(
            '{% import "commerce/products/_fields" as productFields %}{{ productFields.singleVariantFields(product, product.getType()) }}',
            null,
            ['product' => $this],
            Craft::$app->getView()::TEMPLATE_MODE_CP
        );

        $html[] = parent::getSidebarHtml(false);

        // Custom styling
        $html[] = Html::style('.element-editor > .ee-body > .ee-sidebar > .meta + .meta:not(.read-only) { margin-top: 14px; }');

        if (!$this->getType()->hasVariants) {
            Craft::$app->getView()->registerJs('Craft.Commerce.initUnlimitedStockCheckbox($(".ee-sidebar"));');
        }

        return implode('', $html);
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
            return implode(' ', ArrayHelper::getColumn($this->getVariants(), 'sku'));
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
            $record->promotable = $this->promotable;
            $record->availableForPurchase = $this->availableForPurchase;
            $record->freeShipping = $this->freeShipping;
            $record->taxCategoryId = $this->taxCategoryId;
            $record->shippingCategoryId = $this->shippingCategoryId;

            $defaultVariant = $this->getDefaultVariant();
            $record->defaultVariantId = $defaultVariant->id ?? null;
            $record->defaultSku = $defaultVariant->skuAsText ?? '';
            $record->defaultPrice = $defaultVariant->price ?? 0.0;
            $record->defaultHeight = $defaultVariant->height ?? 0;
            $record->defaultLength = $defaultVariant->length ?? 0;
            $record->defaultWidth = $defaultVariant->width ?? 0;
            $record->defaultWeight = $defaultVariant->weight ?? 0;

            // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
            $record->dateUpdated = $this->dateUpdated;
            $record->dateCreated = $this->dateCreated;

            $record->save(false);

            $this->id = $record->id;

            $keepVariantIds = [];
            $oldVariantIds = (new Query())
                ->select('id')
                ->from(Table::VARIANTS)
                ->where(['productId' => $this->id])
                ->column();

            foreach ($this->getVariants(true) as $variant) {
                if ($isNew) {
                    $variant->productId = $this->id;
                    $variant->siteId = $this->siteId;
                }

                $keepVariantIds[] = $variant->id;

                Craft::$app->getElements()->saveElement($variant, false);

                // We already have set the default to the correct variant in beforeSave()
                if ($variant->isDefault) {
                    $this->defaultVariantId = $variant->id;
                    Craft::$app->getDb()->createCommand()->update(Table::PRODUCTS, ['defaultVariantId' => $variant->id], ['id' => $this->id])->execute();
                }
            }

            foreach (array_diff($oldVariantIds, $keepVariantIds) as $deleteId) {
                Craft::$app->getElements()->deleteElementById($deleteId);
            }
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

        /** @var Variant[] $variants */
        $variants = Variant::find()
            ->productId([$this->id, ':empty:'])
            ->status(null)
            ->all();

        $elementsService = Craft::$app->getElements();

        foreach ($variants as $variant) {
            $hardDelete = false;
            $variant->deletedWithProduct = true;

            // The product ID is gone, so it has been hard deleted
            if (!$variant->productId) {
                $hardDelete = true;
                $variant->deletedWithProduct = false;
            }

            $elementsService->deleteElement($variant, $hardDelete);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterRestore(): void
    {
        // Also restore any variants for this element
        /** @var VariantQuery $variantsQuery */
        $variantsQuery = Variant::find()
            ->status(null)
            ->siteId($this->siteId);
        $variantsQuery
            ->productId($this->id)
            ->trashed()
            ->andWhere(['commerce_variants.deletedWithProduct' => true]);

        $variants = $variantsQuery->all();

        Craft::$app->getElements()->restoreElements($variants);
        $this->setVariants($variants);

        parent::afterRestore();
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['typeId', 'shippingCategoryId', 'taxCategoryId'], 'number', 'integerOnly' => true],
            [['postDate', 'expiryDate'], DateTimeValidator::class],
            [
                ['variants'],
                function() {
                    if (empty($this->getVariants(true))) {
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
                    foreach ($this->getVariants(true) as $i => $variant) {
                        if ($this->getScenario() === self::SCENARIO_LIVE && $variant->enabled) {
                            $variant->setScenario(self::SCENARIO_LIVE);
                        }
                        if (!$variant->validate()) {
                            $this->addModelErrors($variant, "variants[$i]");
                        }
                    }
                },
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        return parent::getFieldLayout() ?? $this->getType()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        $taxCategoryIds = array_keys($this->getType()->getTaxCategories());
        if (!in_array($this->taxCategoryId, $taxCategoryIds, false)) {
            $this->taxCategoryId = $taxCategoryIds[0];
        }

        $shippingCategoryIds = array_keys($this->getType()->getShippingCategories());
        if (!in_array($this->shippingCategoryId, $shippingCategoryIds, false)) {
            $this->shippingCategoryId = $shippingCategoryIds[0];
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
            ];
        }

        return $sources;
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
                    $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($matches[1]);

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
                $actions[] = CreateSale::class;
                $actions[] = CreateDiscount::class;
            }
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('commerce', 'Product')],
            'id' => ['label' => Craft::t('commerce', 'ID')],
            'type' => ['label' => Craft::t('commerce', 'Type')],
            'slug' => ['label' => Craft::t('commerce', 'Slug')],
            'uri' => ['label' => Craft::t('commerce', 'URI')],
            'postDate' => ['label' => Craft::t('commerce', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('commerce', 'Expiry Date')],
            'taxCategory' => ['label' => Craft::t('commerce', 'Tax Category')],
            'shippingCategory' => ['label' => Craft::t('commerce', 'Shipping Category')],
            'freeShipping' => ['label' => Craft::t('commerce', 'Free Shipping?')],
            'promotable' => ['label' => Craft::t('commerce', 'Promotable?')],
            'availableForPurchase' => ['label' => Craft::t('commerce', 'Available for purchase?')],
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
    protected static function defineSearchableAttributes(): array
    {
        return [
            'defaultSku',
            'sku',
        ];
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
    protected function tableAttributeHtml(string $attribute): string
    {
        $productType = $this->getType();

        switch ($attribute) {
            case 'type':
            {
                return Craft::t('site', $productType->name);
            }
            case 'defaultSku':
            {
                if ($this->defaultSku === null) {
                    return '';
                }

                return PurchasableHelper::isTempSku($this->defaultSku) ? '' : Html::encode($this->defaultSku);
            }
            case 'taxCategory':
            {
                $taxCategory = $this->getTaxCategory();

                return Craft::t('site', $taxCategory->name);
            }
            case 'shippingCategory':
            {
                $shippingCategory = $this->getShippingCategory();

                return Craft::t('site', $shippingCategory->name);
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
                    $stock += $variant->stock;
                    if ($variant->hasUnlimitedStock) {
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
            case 'availableForPurchase':
            case 'promotable':
            case 'freeShipping':
            {
                return ($this->$attribute ? '<span data-icon="check" title="' . Craft::t('commerce', 'Yes') . '"></span>' : '');
            }
            case 'variants':
            {
                $value = $this->getVariants(true);
                $first = array_shift($value);
                $html = Cp::elementHtml($first);

                if (!empty($value)) {
                    $otherHtml = '';
                    foreach ($value as $other) {
                        $otherHtml .= Cp::elementHtml($other);
                    }
                    $html .= Html::tag('span', '+' . Craft::$app->getFormatter()->asInteger(count($value)), [
                        'title' => implode(', ', ArrayHelper::getColumn($value, 'title')),
                        'class' => 'btn small',
                        'role' => 'button',
                        'onclick' => 'jQuery(this).replaceWith(' . Json::encode($otherHtml) . ')',
                    ]);
                }

                return $html;
            }
            default:
            {
                return parent::tableAttributeHtml($attribute);
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
        /** @var Product|null $original */
        $original = $this->duplicateOf;
        if ($original) {
            $variants = Plugin::getInstance()->getVariants()->getAllVariantsByProductId($original->id, $original->siteId);
            $newVariants = [];
            foreach ($variants as $variant) {
                $variant->sku .= '-1';
                $variant = Craft::$app->getElements()->duplicateElement($variant, ['product' => $this]);
                $newVariants[] = $variant;
            }
            $this->setVariants($newVariants);
        }

        parent::afterPropagate($isNew);
    }
}
