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
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\events\CustomizeProductSnapshotDataEvent;
use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;
use craft\commerce\events\CustomizeVariantSnapshotDataEvent;
use craft\commerce\events\CustomizeVariantSnapshotFieldsEvent;
use craft\commerce\models\LineItem;
use craft\commerce\models\ProductType;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Variant as VariantRecord;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
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
 * @property Sale[] $salesApplied sales models which are currently affecting the salePrice of this purchasable
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Variant extends Purchasable
{
    // Constants
    // =========================================================================

    /**
     * @event craft\commerce\events\CustomizeVariantSnapshotFieldsEvent This event is raised before a variant's snapshot is captured
     *
     * Plugins can get notified before we capture a variant's field data, and customize which fields are included.
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\events\CustomizeVariantSnapshotFieldsEvent;
     *
     * Event::on(Variant::class, Variant::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT, function(CustomizeVariantSnapshotFieldsEvent $e) {
     *     $variant = $e->variant;
     *     $fields = $e->fields;
     *     // Modify fields, or set to `null` to capture all.
     * });
     * ```
     */
    const EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT = 'beforeCaptureVariantSnapshot';

    /**
     * @event craft\commerce\events\CustomizeVariantSnapshotFieldsEvent This event is raised after a variant's snapshot is captured.
     *
     * Plugins can get notified after we capture a variant's field data, and customize, extend, or redact the data to be persisted.
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\events\CustomizeVariantSnapshotDataEvent;
     *
     * Event::on(Variant::class, Variant::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT, function(CustomizeVariantSnapshotFieldsEvent $e) {
     *     $variant = $e->variant;
     *     $data = $e->fieldData;
     *     // Modify or redact captured `$data`...
     * });
     * ```
     */
    const EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT = 'afterCaptureVariantSnapshot';

    /**
     * @event craft\commerce\events\CustomizeProductSnapshotFieldsEvent This event is raised before a product snapshot is captured.
     *
     * Plugins can get notified before we capture a product's field data, and
     * customize which fields are included.
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;
     *
     * Event::on(Variant::class, Variant::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT, function(CustomizeProductSnapshotFieldsEvent $e) {
     *     $product = $e->product;
     *     $fields = $e->fields;
     *     // Modify fields, or set to `null` to capture all.
     * });
     * ```
     */
    const EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT = 'beforeCaptureProductSnapshot';

    /**
     * @event craft\commerce\events\CustomizeProductSnapshotDataEvent This event is raised before a product snapshot is captured
     *
     * Plugins can get notified after we capture a product's field data, and customize, extend, or redact the data to be persisted.
     *
     * ```php
     * use craft\commerce\elements\Variant;
     * use craft\commerce\events\CustomizeProductSnapshotDataEvent;
     *
     * Event::on(Variant::class, Variant::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT, function(CustomizeProductSnapshotFieldsEvent $e) {
     *     $product = $e->product;
     *     $data = $e->fieldData;
     *     // Modify or redact captured `$data`...
     * });
     * ```
     */
    const EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT = 'afterCaptureProductSnapshot';


    // Properties
    // =========================================================================

    /**
     * @var int $productId
     */
    public $productId;

    /**
     * @var int $isDefault
     */
    public $isDefault;

    /**
     * @inheritdoc
     */
    public $sku;

    /**
     * @inheritdoc
     */
    public $price;

    /**
     * @var int $sortOrder
     */
    public $sortOrder;

    /**
     * @var int $width
     */
    public $width;

    /**
     * @var int $height
     */
    public $height;

    /**
     * @var int $length
     */
    public $length;

    /**
     * @var int $weight
     */
    public $weight;

    /**
     * @var int $stock
     */
    public $stock;

    /**
     * @var int $hasUnlimitedStock
     */
    public $hasUnlimitedStock;

    /**
     * @var int $minQty
     */
    public $minQty;

    /**
     * @var int $maxQty
     */
    public $maxQty;

    /**
     * @var bool Whether the variant was deleted along with its product
     * @see beforeDelete()
     */
    public $deletedWithProduct = false;

    /**
     * @var Product The product that this variant is associated with.
     * @see getProduct()
     * @see setProduct()
     */
    private $_product;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        $product = $this->getProduct();

        // Use a combined Product and Variant title, if the variant belongs to a product with other variants.
        if ($product && $product->getType()->hasVariants) {
            return "{$this->product}: {$this->title}";
        } else {
            return parent::__toString();
        }
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
    public static function refHandle()
    {
        return 'variant';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        $rules[] = [['sku'], 'string', 'max' => 255];
        $rules[] = [['sku', 'price'], 'required'];
        $rules[] = [['price'], 'number'];
        $rules[] = [
            ['stock'], 'required', 'when' => function($model) {
                /** @var Variant $model */
                return !$model->hasUnlimitedStock;
            }
        ];
        $rules[] = [
            ['stock'], 'number', 'when' => function($model) {
                /** @var Variant $model */
                return !$model->hasUnlimitedStock;
            }
        ];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'product';
        return $names;
    }

    /**
     * Returns an array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @return Sale[]
     * @deprecated
     */
    public function getSalesApplied(): array
    {
        Craft::$app->getDeprecator()->log('Variant::getSalesApplied()', 'The getSalesApplied() function has been deprecated. Use getSales() instead.');

        return $this->getSales();
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return parent::getFieldLayout() ?? $this->getProduct()->getType()->getVariantFieldLayout();
    }

    /**
     * Returns the product associated with this variant.
     *
     * @return Product|null The product associated with this variant, or null if it isn’t known
     * @throws InvalidConfigException if the product ID is missing from the variant
     */
    public function getProduct()
    {
        if ($this->_product !== null) {
            return $this->_product;
        }

        if ($this->productId === null) {
            throw new InvalidConfigException('Variant is missing its product');
        }

        $product = Product::find()
            ->id($this->productId)
            ->siteId($this->siteId)
            ->anyStatus()
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
     */
    public function setProduct(Product $product)
    {
        if ($product->siteId) {
            $this->siteId = $product->siteId;
        }

        if ($product->id) {
            $this->productId = $product->id;
        }

        $this->_product = $product;
    }

    /**
     * Returns the product title and variants title together for variable products.
     *
     * @return string
     */
    public function getDescription(): string
    {
        $format = $this->getProduct()->getType()->descriptionFormat;

        if ($format) {
            return Craft::$app->getView()->renderObjectTemplate($format, $this);
        }

        // If title is not set yet default to blank string
        return $this->title ?? '';
    }

    /**
     * Updates the title based on titleFormat, or sets it to the same title as the product.
     *
     * @param Product $product
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function updateTitle(Product $product)
    {
        $type = $product->getType();
        // Use the product type's titleFormat if the title field is not shown
        if (!$type->hasVariantTitleField && $type->hasVariants && $type->titleFormat) {
            // Make sure that the locale has been loaded in case the title format has any Date/Time fields
            Craft::$app->getLocale();
            // Set Craft to the products's site's language, in case the title format has any static translations
            $language = Craft::$app->language;
            Craft::$app->language = $this->getSite()->language;
            $this->title = Craft::$app->getView()->renderObjectTemplate($type->titleFormat, $this);
            Craft::$app->language = $language;
        }

        if (!$type->hasVariants) {
            $this->title = $product->title;
        }
    }


    /**
     * @param Product $product
     * @throws Throwable
     */
    public function updateSku(Product $product)
    {
        $type = $product->getType();
        // If we have a blank SKU, generate from product type's skuFormat
        if (!$this->sku && $type->skuFormat) {
            // Make sure that the locale has been loaded in case the title format has any Date/Time fields
            Craft::$app->getLocale();
            // Set Craft to the products's site's language, in case the title format has any static translations
            $language = Craft::$app->language;
            Craft::$app->language = $this->getSite()->language;
            $this->sku = Craft::$app->getView()->renderObjectTemplate($type->skuFormat, $this);
            Craft::$app->language = $language;
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();

        return array_merge($labels, ['sku' => 'SKU']);
    }

    /**
     * @return bool
     */
    public function getIsEditable(): bool
    {
        $product = $this->getProduct();

        if ($product) {
            return $product->getIsEditable();
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return $this->getProduct() ? $this->getProduct()->getCpEditUrl() : null;
    }

    /**
     * @inheritdoc
     */
    public function getUrl(): string
    {
        return $this->product->url . '?variant=' . $this->id;
    }

    /**
     * Cache on the purchasable table.
     *
     * @inheritdoc
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     *
     * @return array
     * @throws InvalidConfigException
     */
    public function getSnapshot(): array
    {
        $data = [];
        $data['onSale'] = $this->getOnSale();
        $data['cpEditUrl'] = $this->getCpEditUrl();

        // Default Product custom field handles
        $productFields = [];
        $productFieldsEvent = new CustomizeProductSnapshotFieldsEvent([
            'product' => $this->getProduct(),
            'fields' => $productFields
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
                foreach ($fieldLayout->getFields() as $field) {
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
                'fieldData' => $data['product']
            ]);
        } else {
            $productDataEvent = new CustomizeProductSnapshotDataEvent([
                'product' => $this->getProduct(),
                'fieldData' => []
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
            'fields' => $variantFields
        ]);

        // Allow plugins to modify fields to be fetched
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT)) {
            $this->trigger(self::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT, $variantFieldsEvent);
        }

        $variantAttributes = $this->attributes();

        // Remove custom fields
        if (($fieldLayout = $this->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getFields() as $field) {
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
            'fieldData' => $variantData
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
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategoryId(): int
    {
        return $this->getProduct()->taxCategoryId;
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategoryId(): int
    {
        return $this->getProduct()->shippingCategoryId;
    }

    /**
     * Returns whether this variant has stock.
     *
     * @return bool
     */
    public function hasStock(): bool
    {
        return $this->stock > 0 || $this->hasUnlimitedStock;
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        $isShippable = $this->getIsShippable();
        return $isShippable && $this->getProduct()->freeShipping;
    }

    /**
     * @inheritdoc
     */
    public function getLineItemRules(LineItem $lineItem): array
    {
        $order = $lineItem->getOrder();

        // After the order is complete shouldn't check things like stock being available or the purchasable being around since they are irrelevant.
        if ($order && $order->isCompleted) {
            return [];
        }

        $getQty = function(LineItem $lineItem) {
            $qty = 0;
            foreach ($lineItem->getOrder()->getLineItems() as $item) {
                if ($item->id !== null && $item->id == $lineItem->id) {
                    $qty += $lineItem->qty;
                } elseif ($item->purchasableId == $lineItem->purchasableId) {
                    $qty += $item->qty;
                }
            }
            return $qty;
        };

        return [
            // an inline validator defined as an anonymous function
            [
                'purchasableId',
                function($attribute, $params, Validator $validator) use ($lineItem) {
                    /** @var Purchasable $purchasable */
                    $purchasable = $lineItem->getPurchasable();
                    if ($purchasable->getStatus() != Element::STATUS_ENABLED) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'The item is not enabled for sale.'));
                    }
                }
            ],
            [
                'qty',
                function($attribute, $params, Validator $validator) use ($lineItem, $getQty) {
                    if (!$this->hasStock()) {
                        $error = Craft::t('commerce', '"{description}" is currently out of stock.', ['description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->hasStock() && !$this->hasUnlimitedStock && $getQty($lineItem) > $this->stock) {
                        $error = Craft::t('commerce', 'There are only {num} "{description}" items left in stock.', ['num' => $this->stock, 'description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->minQty > 1 && $getQty($lineItem) < $this->minQty) {
                        $error = Craft::t('commerce', 'Minimum order quantity for this item is {num}.', ['num' => $this->minQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->maxQty != 0 && $getQty($lineItem) > $this->maxQty) {
                        $error = Craft::t('commerce', 'Maximum order quantity for this item is {num}.', ['num' => $this->maxQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }
                },
            ],
            [['qty'], 'integer', 'min' => 1, 'skipOnError' => false]
        ];
    }

    /**
     * @inheritdoc
     * @return VariantQuery The newly created [[VariantQuery]] instance.
     */
    public static function find(): ElementQueryInterface
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
    public static function eagerLoadingMap(array $sourceElements, string $handle): array
    {
        if ($handle == 'product') {
            // Get the source element IDs
            $sourceElementIds = [];

            foreach ($sourceElements as $sourceElement) {
                $sourceElementIds[] = $sourceElement->id;
            }

            $map = (new Query())
                ->select('id as source, productId as target')
                ->from('commerce_variants')
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return [
                'elementType' => Product::class,
                'map' => $map
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function populateLineItem(LineItem $lineItem)
    {
        // Since we do not have a proper stock reservation system, we need deduct stock if they have more in the cart than is available, and to do this quietly.
        // If this occurs in the payment request, the user will be notified the order has changed.
        if (($lineItem->qty > $this->stock) && !$this->hasUnlimitedStock) {
            $lineItem->qty = $this->stock;
        }

        $lineItem->weight = (float)$this->weight; //converting nulls
        $lineItem->height = (float)$this->height; //converting nulls
        $lineItem->length = (float)$this->length; //converting nulls
        $lineItem->width = (float)$this->width; //converting nulls

        return null;
    }

    /**
     * Returns a promotion category related to this element if the category is related to the product OR the variant.
     *
     * @return array
     */
    public function getPromotionRelationSource(): array
    {
        return [$this->id, $this->getProduct()->id];
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return (bool)$this->getProduct()->promotable;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
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
        $record->sku = $this->sku;
        $record->price = $this->price;
        $record->width = $this->width;
        $record->height = $this->height;
        $record->length = $this->length;
        $record->weight = $this->weight;
        $record->minQty = $this->minQty;
        $record->maxQty = $this->maxQty;
        $record->stock = $this->stock;
        $record->isDefault = $this->isDefault;
        $record->sortOrder = $this->sortOrder;
        $record->hasUnlimitedStock = $this->hasUnlimitedStock;

        if (!$this->getProduct()->getType()->hasDimensions) {
            $record->width = $this->width = 0;
            $record->height = $this->height = 0;
            $record->length = $this->length = 0;
            $record->weight = $this->weight = 0;
        }

        $record->save(false);

        return parent::afterSave($isNew);
    }

    /**
     * Updates Stock count from completed order.
     *
     * @inheritdoc
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem)
    {
        // Don't reduce stock of unlimited items.
        if (!$this->hasUnlimitedStock) {
            // Update the qty in the db directly
            Craft::$app->getDb()->createCommand()->update('{{%commerce_variants}}',
                ['stock' => new Expression('stock - :qty', [':qty' => $lineItem->qty])],
                ['id' => $this->id])->execute();

            // Update the stock
            $this->stock = (new Query())
                ->select(['stock'])
                ->from('{{%commerce_variants}}')
                ->where('id = :variantId', [':variantId' => $this->id])
                ->scalar();

            Craft::$app->getTemplateCaches()->deleteCachesByElementId($this->id);
        }
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        if ($this->getProduct() && !$this->getProduct()->availableForPurchase) {
            return false;
        }

        if ($this->getStatus() !== Element::STATUS_ENABLED) {
            return false;
        }

        return $this->stock >= 1 || $this->hasUnlimitedStock;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        $productStatus = $this->getProduct()->getStatus();
        if ($productStatus != Product::STATUS_LIVE) {
            return Element::STATUS_DISABLED;
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle == 'product') {
            $product = $elements[0] ?? null;
            $this->setProduct($product);
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
    public function beforeValidate()
    {
        $product = $this->getProduct();

        $this->updateTitle($product);
        $this->updateSku($product);

        // Zero out stock if unlimited stock is turned on
        if ($this->hasUnlimitedStock) {
            $this->stock = 0;
        }

        $this->fieldLayoutId = $product->getType()->variantFieldLayoutId;

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

        Craft::$app->getDb()->createCommand()
            ->update('{{%commerce_variants}}', [
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
            ->from('{{%commerce_purchasables}} p')
            ->leftJoin(Table::ELEMENTS . ' e', '[[p.id]]=[[e.id]]')
            ->where(['[[e.dateDeleted]]' => null, '[[p.sku]]' => $this->getSku()])
            ->andWhere(['not', ['[[e.id]]' => $this->getId()]])
            ->count();

        if ($found) {
            // Set new SKU in memory
            $this->sku = $this->getSku() . '-1';

            // Update variant table with new SKU
            Craft::$app->getDb()->createCommand()->update('{{%commerce_variants}}',
                ['sku' => $this->sku],
                ['id' => $this->getId()]
            )->execute();

            if ($this->isDefault) {
                Craft::$app->getDb()->createCommand()->update('{{%commerce_products}}',
                    ['defaultSku' => $this->sku],
                    ['id' => $this->productId]
                )->execute();
            }

            // Update purchasable table with new SKU
            Craft::$app->getDb()->createCommand()->update('{{%commerce_purchasables}}',
                ['sku' => $this->sku],
                ['id' => $this->getId()]
            )->execute();
        }

        return true;
    }

    // Protected Methods
    // =========================================================================

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
        return [
            'title' => Craft::t('commerce', 'Title'),
            'product' => Craft::t('commerce', 'Product'),
            'sku' => Craft::t('commerce', 'SKU'),
            'price' => Craft::t('commerce', 'Price'),
            'width' => Craft::t('commerce', 'Width ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'height' => Craft::t('commerce', 'Height ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'length' => Craft::t('commerce', 'Length ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'weight' => Craft::t('commerce', 'Weight ({unit})', ['unit' => Plugin::getInstance()->getSettings()->weightUnits]),
            'stock' => Craft::t('commerce', 'Stock'),
            'minQty' => Craft::t('commerce', 'Quantities')
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        $attributes[] = 'title';
        $attributes[] = 'product';
        $attributes[] = 'sku';
        $attributes[] = 'price';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['sku', 'price', 'width', 'height', 'length', 'weight', 'stock', 'hasUnlimitedStock', 'minQty', 'maxQty'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title')
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        /* @var $productType ProductType */
        $productType = $this->product->getType();

        switch ($attribute) {
            case 'sku':
                {
                    return $this->sku;
                }
            case 'product':
                {
                    return $this->product->title;
                }
            case 'price':
                {
                    $code = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

                    return Craft::$app->getLocale()->getFormatter()->asCurrency($this->$attribute, strtoupper($code));
                }
            case 'weight':
                {
                    if ($productType->hasDimensions) {
                        return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->weightUnits;
                    }

                    return '';
                }
            case 'length':
            case 'width':
            case 'height':
                {
                    if ($productType->hasDimensions) {
                        return Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits;
                    }

                    return '';
                }
            default:
                {
                    return parent::tableAttributeHtml($attribute);
                }
        }
    }
}
