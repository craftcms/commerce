<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements;

use Craft;
use craft\base\Element;
use craft\commerce\base\Purchasable;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\variants\VariantCondition;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\events\CustomizeProductSnapshotDataEvent;
use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;
use craft\commerce\events\CustomizeVariantSnapshotDataEvent;
use craft\commerce\events\CustomizeVariantSnapshotFieldsEvent;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\ProductType;
use craft\commerce\models\Sale;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\Variant as VariantRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\conditions\ElementConditionInterface;
use craft\gql\types\DateTime;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\Expression;
use yii\validators\Validator;

/**
 * Variant model.
 *
 * @property string $eagerLoadedElements some eager-loaded elements on a given handle
 * @property bool $onSale
 * @property Product $product the product associated with this variant
 * @property Sale[] $sales sales models which are currently affecting the salePrice of this purchasable
 * @property string $priceAsCurrency
 * @property DateTime|null $dateUpdated
 * @property DateTime|null $dateCreated
 * @property-read string[] $cacheTags
 * @property-read string $gqlTypeName
 * @property-read string $skuAsText
 * @property string $salePriceAsCurrency
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variant extends Purchasable
{
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
     * @var int|null $productId
     */
    public ?int $productId = null;

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
     * @var Product|null The product that this variant is associated with.
     * @see getProduct()
     * @see setProduct()
     */
    private ?Product $_product = null;

    /**
     * @throws InvalidConfigException
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

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
            'price',
            'salePrice',
        ];
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        $product = $this->getProduct();

        // Use a combined Product and Variant title, if the variant belongs to a product with other variants.
        if ($product && $product->getType()->hasVariants) {
            return "$this->product: $this->title";
        }

        return parent::__toString();
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
     * @return VariantCondition
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(VariantCondition::class, [static::class]);
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
     */
    public function getFieldLayout(): ?FieldLayout
    {
        $fieldLayout = parent::getFieldLayout();

        // TODO: If we ever resave all products in a migration, we can remove this fallback and just use the default getFieldLayout() #COM-41
        if (!$fieldLayout && $this->productId) {
            $fieldLayout = $this->getProduct()->getType()->getVariantFieldLayout();
        }

        return $fieldLayout;
    }

    /**
     * Returns the product associated with this variant.
     *
     * @return Product|null The product associated with this variant, or null if it isn’t known
     * @throws InvalidConfigException if the product ID is missing from the variant
     */
    public function getProduct(): ?Product
    {
        if ($this->_product !== null) {
            return $this->_product;
        }

        if ($this->productId === null) {
            throw new InvalidConfigException('Variant is missing its product');
        }

        /** @var Product|null $product */
        $product = Product::find()
            ->id($this->productId)
            ->siteId($this->siteId)
            ->status(null)
            ->trashed(null)
            ->one();

        if ($product === null) {
            throw new InvalidConfigException('Invalid product ID: ' . $this->productId);
        }

        return $this->_product = $product;
    }

    /**
     * Sets the product associated with this variant.
     *
     * @param Product $product The product associated with this variant
     * @throws InvalidConfigException
     */
    public function setProduct(Product $product): void
    {
        if ($product->siteId) {
            $this->siteId = $product->siteId;
        }

        if ($product->id) {
            $this->productId = $product->id;
        }

        $this->fieldLayoutId = $product->getType()->variantFieldLayoutId;

        $this->_product = $product;
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

        if ($format = $this->getProduct()->getType()->descriptionFormat) {
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
        if (!$type->hasVariantTitleField && $type->hasVariants && $type->variantTitleFormat) {
            // Make sure that the locale has been loaded in case the title format has any Date/Time fields
            Craft::$app->getLocale();
            // Set Craft to the products's site's language, in case the title format has any static translations
            $language = Craft::$app->language;
            Craft::$app->language = $this->getSite()->language;
            $this->title = Craft::$app->getView()->renderObjectTemplate($type->variantTitleFormat, $this);
            Craft::$app->language = $language;
        }

        if (!$type->hasVariants) {
            $this->title = $product->title;
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
            "product:$this->productId",
        ];
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('commerce/variants/' . $this->id . ($this->getSku() ? '-' . $this->getSku() : ''));
    }

    /**
     * @inheritdoc
     */
    public function getUrl(): ?string
    {
        return $this->product->url . '?variant=' . $this->id;
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
            'product' => $this->getProduct(),
            'fields' => $productFields,
        ]);

        // Allow plugins to modify Product fields to be fetched
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT, $productFieldsEvent);
        }

        // Product Attributes
        if ($product = $this->getProduct()) {
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

            $data['product'] = $this->getProduct()->toArray($productAttributes, [], false);

            $productDataEvent = new CustomizeProductSnapshotDataEvent([
                'product' => $this->getProduct(),
                'fieldData' => $data['product'],
            ]);
        } else {
            $productDataEvent = new CustomizeProductSnapshotDataEvent([
                'product' => $this->getProduct(),
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
     */
    public function hasFreeShipping(): bool
    {
        $isShippable = $this->getIsShippable(); // Same as Plugin::getInstance()->getPurchasables()->isPurchasableShippable since this has no context
        return $isShippable && $this->getProduct()->freeShipping;
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
                ->select('id as source, productId as target')
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

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * Returns a promotion category related to this element if the category is related to the product OR the variant.
     */
    public function getPromotionRelationSource(): array
    {
        return [$this->id, $this->getProduct()->id];
    }

    /**
     * @throws InvalidConfigException
     * @since 3.1
     */
    public function getGqlTypeName(): string
    {
        $product = $this->getProduct();

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

            $record->productId = $this->productId;
            $record->isDefault = $this->isDefault;
            $record->sortOrder = $this->sortOrder;

            // We want to always have the same date as the element table, based on the logic for updating these in the element service i.e resaving
            $record->dateUpdated = $this->dateUpdated;
            $record->dateCreated = $this->dateCreated;

            $record->save(false);
        }

        parent::afterSave($isNew);
    }

    /**
     * Updates Stock count from completed order.
     *
     * @inheritdoc
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem): void
    {
        // Don't reduce stock of unlimited items.
        if (!$this->hasUnlimitedStock) {
            // Update the qty in the db directly
            Craft::$app->getDb()->createCommand()->update(Table::VARIANTS,
                ['stock' => new Expression('stock - :qty', [':qty' => $lineItem->qty])],
                ['id' => $this->id])->execute();

            // Update the stock
            $this->stock = (new Query())
                ->select(['stock'])
                ->from(Table::VARIANTS)
                ->where('id = :variantId', [':variantId' => $this->id])
                ->scalar();

            Craft::$app->getElements()->invalidateCachesForElement($this);
        }
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        $product = $this->getProduct();

        if (!$product) {
            return false;
        }

        // is the parent product available for sale?
        if (!$product->availableForPurchase) {
            return false;
        }

        // is the variant enabled?
        if ($this->getStatus() !== Element::STATUS_ENABLED) {
            return false;
        }

        // is parent product enabled?
        if ($product->getStatus() !== Product::STATUS_LIVE) {
            return false;
        }

        if (!$this->hasUnlimitedStock && $this->stock < 1) {
            return false;
        }

        // Temporary SKU can not be added to the cart
        if (PurchasableHelper::isTempSku($this->getSku())) {
            return false;
        }

        return $this->stock >= 1 || $this->hasUnlimitedStock;
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements): void
    {
        if ($handle == 'product') {
            $product = $elements[0] ?? null;
            if ($product instanceof Product) {
                $this->setProduct($product);
            }
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
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
     */
    public function beforeValidate(): bool
    {
        $product = $this->getProduct();

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
        // Set the field layout
        $productType = $this->getProduct()->getType();
        $this->fieldLayoutId = $productType->variantFieldLayoutId;

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
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
                    ['id' => $this->productId]
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
            return $this->getProduct()->title;
        }

        return parent::getSearchKeywords($attribute);
    }


    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        return Product::sources($context);
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'product' => Craft::t('commerce', 'Product'),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [...parent::defineDefaultTableAttributes($source), ...['product']];
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
    protected function tableAttributeHtml(string $attribute): string
    {
        if ($attribute === 'product') {
            $product = $this->getProduct();
            if (!$product) {
                return '';
            }

            return sprintf('<span class="status %s"></span> %s', $product->getStatus(), Html::encode($product->title));
        }

        return parent::tableAttributeHtml($attribute);
    }
}
