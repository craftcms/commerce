<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\PurchasableStore as PurchasableStoreModel;
use craft\commerce\models\Sale;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\commerce\records\Purchasable as PurchasableRecord;
use craft\commerce\records\PurchasableStore;
use craft\errors\SiteNotFoundException;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Typecast;
use craft\validators\UniqueValidator;
use Illuminate\Support\Collection;
use yii\base\InvalidConfigException;
use yii\validators\Validator;

/**
 * Base Purchasable
 *
 * @property string $description the element's title or any additional descriptive information
 * @property bool $isAvailable whether the purchasable is currently available for purchase
 * @property bool $isPromotable whether this purchasable can be subject to discounts or sales
 * @property bool $onPromotion whether this purchasable is currently on sale at a promotional price
 * @property float $promotionRelationSource The source for any promotion category relation
 * @property float $price the base price the item will be added to the line item with
 * @property-read float $salePrice the base price the item will be added to the line item with
 * @property-read string $priceAsCurrency the base price the item will be added to the line item with
 * @property-read string $salePriceAsCurrency the base price the item will be added to the line item with
 * @property int $shippingCategoryId the purchasable's shipping category ID
 * @property string $sku a unique code as per the commerce_purchasables table
 * @property array $snapshot
 * @property bool $isShippable
 * @property bool $isTaxable
 * @property int $taxCategoryId the purchasable's tax category ID
 * @property bool $hasUnlimitedStock
 * @property int $stock
 * @property int $minQty
 * @property int $maxQty
 * @property bool $promotable
 * @property bool $freeShipping
 * @property bool $availableForPurchase
 * @property float $width
 * @property float $height
 * @property float $length
 * @property float $weight
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class Purchasable extends Element implements PurchasableInterface
{
    /**
     * @var float[]|null
     */
    private ?array $_salePrice = null;

    /**
     * @var float[]|null
     */
    private ?array $_price = null;

    /**
     * The store based on the `siteId` of the instance of the purchasable.
     *
     * @var Store|null
     */
    private ?Store $_store = null;

    /**
     * @var float[]|null
     */
    private ?array $_promotionalPrice = null;

    /**
     * @var Collection|null
     * @since 5.0.0
     */
    private ?Collection $_purchasableStores = null;

    /**
     * @var string SKU
     * @see getSku()
     * @see setSku()
     */
    private string $_sku = '';

    /**
     * @var int|null Tax category ID
     * @since 5.0.0
     */
    public ?int $taxCategoryId = null;

    /**
     * @var int|null Shipping category ID
     * @since 5.0.0
     */
    public ?int $shippingCategoryId = null;

    /**
     * @var float|null $width
     * @since 5.0.0
     */
    public ?float $width = null;

    /**
     * @var float|null $height
     * @since 5.0.0
     */
    public ?float $height = null;

    /**
     * @var float|null $length
     * @since 5.0.0
     */
    public ?float $length = null;

    /**
     * @var float|null $weight
     * @since 5.0.0
     */
    public ?float $weight = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();

        $names[] = 'isAvailable';
        $names[] = 'isPromotable';
        $names[] = 'basePrice';
        $names[] = 'basePromotionalPrice';
        $names[] = 'price';
        $names[] = 'promotionalPrice';
        $names[] = 'onPromotion';
        $names[] = 'salePrice';
        $names[] = 'sku';
        return $names;
    }

    /**
     * @inheritdoc
     * @since 3.2.9
     */
    public function fields(): array
    {
        $fields = parent::fields();

        $fields['salePrice'] = 'salePrice';
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();

        $names[] = 'description';
        $names[] = 'sales';
        $names[] = 'snapshot';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        if (!empty($values)) {
            // @TODO figure out the cleanest way to do this
            $values['price'] = $values['basePrice'];
            $values['promotionalPrice'] = $values['basePromotionalPrice'];
            Typecast::properties(PurchasableStoreModel::class, $values);
            $values['basePrice'] = $values['price'];
            $values['basePromotionalPrice'] = $values['promotionalPrice'];
            unset($values['price'], $values['promotionalPrice']);
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $classNameParts = explode('\\', static::class);

        return array_pop($classNameParts);
    }

    /**
     * @inheritdoc
     */
    public function getStore(): Store
    {
        if ($this->_store === null) {
            if ($this->siteId === null) {
                throw new InvalidConfigException('Purchasable::siteId cannot be null');
            }

            // @TODO implement this when we have site to store mapping
            // $this->_store = Plugin::getInstance()->getStores()->getStoreBySiteId($this->siteId) ?? Plugin::getInstance()->getStores()->getCurrentStore();
            $this->_store = Plugin::getInstance()->getStores()->getCurrentStore();
        }

        return $this->_store;
    }

    /**
     * @param array|Collection<PurchasableStoreModel>|null $purchasableStores
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function setPurchasableStores(array|Collection|null $purchasableStores): void
    {
        if ($purchasableStores === null) {
            $purchasableStores = [];
        }

        if (is_array($purchasableStores) && !empty($purchasableStores)) {
            foreach ($purchasableStores as &$purchasableStore) {
                if ($purchasableStore instanceof PurchasableStoreModel) {
                    continue;
                }

                // Remove any completely blank rows
                if (!isset($purchasableStore['purchasableId']) || !isset($purchasableStore['storeId'])) {
                    $purchasableStore = null;
                    continue;
                }

                $purchasableStore = Craft::createObject(array_merge([
                    'class' => PurchasableStoreModel::class,
                ], $purchasableStore));
            }

            // Remove blank rows
            $purchasableStores = array_filter($purchasableStores);
        }

        $this->_purchasableStores = is_array($purchasableStores) ? collect($purchasableStores) : $purchasableStores;
    }

    /**
     * @return Collection
     * @since 5.0.0
     */
    public function getPurchasableStores(): Collection
    {
        return $this->_purchasableStores ?? collect([]);
    }

    /**
     * @param string $key
     * @param Store|null $store
     * @return mixed
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @since 5.0.0
     */
    public function getPurchasableStoreValue(string $key, ?Store $store = null): mixed
    {
        $store = $store ?? $this->getStore();

        $purchasableStore = $this->getPurchasableStores()->firstWhere('storeId', $store->id);

        if (!$purchasableStore) {
            return null;
        }

        if (!$purchasableStore->hasProperty($key)) {
            throw new InvalidConfigException('Invalid purchasable store key: ' . $key);
        }

        return $purchasableStore->$key;
    }

    /**
     * @param string $key
     * @param mixed|null $value
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function setPurchasableStoreValue(string $key, mixed $value = null, ?Store $store = null): void
    {
        $store = $store ?? $this->getStore();

        $purchasableStore = $this->getPurchasableStores()->firstWhere('storeId', $store->id);

        if (!$purchasableStore) {
            $purchasableStore = Craft::createObject([
                'class' => PurchasableStoreModel::class,
                'storeId' => $store->id,
                'purchasableId' => $this->id,
            ]);
            $this->getPurchasableStores()->add($purchasableStore);
        }

        if (!$purchasableStore->hasProperty($key)) {
            throw new InvalidConfigException('Invalid purchasable store key: ' . $key);
        }

        $purchasableStore->$key = $value;
    }

    /**
     * @param float|null $price
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setBasePromotionalPrice(?float $price, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('promotionalPrice', $price, $store);
    }

    /**
     * @inheritdoc
     */
    public function getBasePromotionalPrice(?Store $store = null): ?float
    {
        return $this->getPurchasableStoreValue('promotionalPrice', $store);
    }

    /**
     * @param bool $freeShipping
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setFreeShipping(bool $freeShipping, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('freeShipping', $freeShipping, $store);
    }

    /**
     * @inheritdoc
     */
    public function getFreeShipping(?Store $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('freeShipping', $store);
    }

    /**
     * @param bool $promotable
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setPromotable(bool $promotable, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('promotable', $promotable, $store);
    }

    /**
     * @inheritdoc
     */
    public function getPromotable(?Store $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('promotable', $store);
    }

    /**
     * @param bool $availableForPurchase
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setAvailableForPurchase(bool $availableForPurchase, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('availableForPurchase', $availableForPurchase, $store);
    }

    /**
     * @inheritdoc
     */
    public function getAvailableForPurchase(?Store $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('availableForPurchase', $store);
    }

    /**
     * @param int|null $minQty
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setMinQty(?int $minQty, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('minQty', $minQty, $store);
    }

    /**
     * @inheritdoc
     */
    public function getMinQty(?Store $store = null): ?int
    {
        return $this->getPurchasableStoreValue('minQty', $store);
    }

    /**
     * @param int|null $maxQty
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setMaxQty(?int $maxQty, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('maxQty', $maxQty, $store);
    }

    /**
     * @inheritdoc
     */
    public function getMaxQty(?Store $store = null): ?int
    {
        return $this->getPurchasableStoreValue('maxQty', $store);
    }

    /**
     * @param bool $hasUnlimitedStock
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setHasUnlimitedStock(bool $hasUnlimitedStock, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('hasUnlimitedStock', $hasUnlimitedStock, $store);
    }

    /**
     * @inheritdoc
     */
    public function getHasUnlimitedStock(?Store $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('hasUnlimitedStock', $store);
    }

    /**
     * @inheritdoc
     */
    public function getBasePrice(?Store $store = null): ?float
    {
        return $this->getPurchasableStoreValue('price', $store);
    }

    /**
     * @param float|null $price
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function setBasePrice(?float $price, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('price', $price, $store);
    }

    /**
     * @param float|null $price
     * @param string $storeHandle
     * @return void
     * @since 5.0.0
     */
    public function setPrice(?float $price, string $storeHandle): void
    {
        $this->_price[$storeHandle] = $price;
    }

    /**
     * @param Store|null $store
     * @return float|null
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public function getPrice(?Store $store = null): ?float
    {
        $store = $store ?? $this->getStore();

        if (!isset($this->_price[$store->handle])) {
            // Live get catalog price
            $catalogPrice = Plugin::getInstance()->getCatalogPricing()->getCatalogPrice($this->id, $store->id, Craft::$app->getUser()->getIdentity()?->id, false);
            if ($catalogPrice !== null) {
                $this->setPrice($catalogPrice, $store->handle);
            }
        }

        return $this->_price[$store->handle] ?? $this->getBasePrice($store);
    }

    /**
     * @param Store|null $store
     * @return float|null
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public function getPromotionalPrice(?Store $store = null): ?float
    {
        $store = $store ?? $this->getStore();

        if (!isset($this->_promotionalPrice[$store->handle])) {
            $catalogPromotionalPrice = Plugin::getInstance()->getCatalogPricing()->getCatalogPrice($this->id, $store->id, Craft::$app->getUser()->getIdentity()?->id, true);
            if ($catalogPromotionalPrice !== null) {
                $this->setPromotionalPrice($catalogPromotionalPrice, $store->handle);
            }
        }

        $price = $this->getPrice($store);
        $promotionalPrice = $this->_promotionalPrice[$store->handle] ?? $this->getBasePromotionalPrice($store);

        return ($promotionalPrice !== null && $promotionalPrice < $price) ? $promotionalPrice : null;
    }

    /**
     * @param float|null $price
     * @param string $storeHandle
     * @return void
     */
    public function setPromotionalPrice(?float $price, string $storeHandle): void
    {
        $this->_promotionalPrice[$storeHandle] = $price;
    }

    /**
     * @inheritdoc
     */
    public function getSalePrice(?Store $store = null): ?float
    {
        $store = $store ?? $this->getStore();

        if (empty($this->_salePrice) || !isset($this->_salePrice[$store->handle])) {
            $this->_salePrice[$store->handle] = $this->getPromotionalPrice($store) ?? $this->getPrice($store);
        }

        return $this->_salePrice[$store->handle] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return $this->_sku ?? '';
    }

    /**
     * Returns the SKU as text but returns a blank string if it’s a temp SKU.
     */
    public function getSkuAsText(): string
    {
        $sku = $this->getSku();

        if (PurchasableHelper::isTempSku($sku)) {
            $sku = '';
        }

        return $sku;
    }

    /**
     * @param string|null $sku
     */
    public function setSku(string $sku = null): void
    {
        $this->_sku = $sku;
    }

    /**
     * Returns whether this variant has stock.
     */
    public function hasStock(?Store $store = null): bool
    {
        return $this->getPurchasableStoreValue('stock', $store) > 0 || $this->getPurchasableStoreValue('hasUnlimitedStock', $store);
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategory(): TaxCategory
    {
        return $this->taxCategoryId ? Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId) : Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory();
    }

    /**
     * @param int|null $stock
     * @param Store|null $store
     * @return void
     * @throws InvalidConfigException
     */
    public function setStock(?int $stock, ?Store $store = null): void
    {
        $this->setPurchasableStoreValue('stock', $stock, $store);
    }

    /**
     * @param Store|null $store
     * @return int|null
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @since 5.0.0
     */
    public function getStock(?Store $store = null): ?int
    {
        return $this->getPurchasableStoreValue('stock', $store);
    }

    /**
     * @inheritdoc
     */
    public function getSnapshot(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategory(): ShippingCategory
    {
        return $this->shippingCategoryId ? Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId) : Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory();
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return (string)$this;
    }

    /**
     * @inheritdoc
     */
    public function populateLineItem(LineItem $lineItem): void
    {
        // Since we do not have a proper stock reservation system, we need deduct stock if they have more in the cart than is available, and to do this quietly.
        // If this occurs in the payment request, the user will be notified the order has changed.
        if (($order = $lineItem->getOrder()) && !$order->isCompleted) {
            if (($lineItem->qty > $this->getStock()) && !$this->getHasUnlimitedStock()) {
                $message = Craft::t('commerce', '{description} only has {stock} in stock.', ['description' => $lineItem->getDescription(), 'stock' => $this->getStock()]);
                /** @var OrderNotice $notice */
                $notice = Craft::createObject([
                    'class' => OrderNotice::class,
                    'attributes' => [
                        'type' => 'lineItemSalePriceChanged',
                        'attribute' => "lineItems.$lineItem->id.qty",
                        'message' => $message,
                    ],
                ]);
                $order->addNotice($notice);
                $lineItem->qty = $this->getStock();
            }
        }

        $lineItem->weight = (float)$this->weight; //converting nulls
        $lineItem->height = (float)$this->height; //converting nulls
        $lineItem->length = (float)$this->length; //converting nulls
        $lineItem->width = (float)$this->width; //converting nulls
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

        $lineItemQuantitiesById = [];
        $lineItemQuantitiesByPurchasableId = [];
        foreach ($order->getLineItems() as $item) {
            if ($item->id !== null) {
                $lineItemQuantitiesById[$item->id] = isset($lineItemQuantitiesById[$item->id]) ? $lineItemQuantitiesById[$item->id] + $item->qty : $item->qty;
            } else {
                $lineItemQuantitiesByPurchasableId[$item->purchasableId] = isset($lineItemQuantitiesByPurchasableId[$item->purchasableId]) ? $lineItemQuantitiesByPurchasableId[$item->purchasableId] + $item->qty : $item->qty;
            }
        }


        return [
            // an inline validator defined as an anonymous function
            [
                'purchasableId',
                function($attribute, $params, Validator $validator) use ($lineItem) {
                    $purchasable = $lineItem->getPurchasable();
                    if ($purchasable === null) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'No purchasable available.'));
                    }

                    if ($purchasable->getStatus() != Element::STATUS_ENABLED) {
                        $validator->addError($lineItem, $attribute, Craft::t('commerce', 'The item is not enabled for sale.'));
                    }
                },
            ],
            [
                'qty',
                function($attribute, $params, Validator $validator) use ($lineItem, $lineItemQuantitiesById, $lineItemQuantitiesByPurchasableId) {
                    // @TODO change all attribute calls to pass in the the store from the order `$lineItem->getOrder()->getStore()`
                    if (!$this->hasStock()) {
                        $error = Craft::t('commerce', '“{description}” is currently out of stock.', ['description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    $lineItemQty = $lineItem->id !== null ? $lineItemQuantitiesById[$lineItem->id] : $lineItemQuantitiesByPurchasableId[$lineItem->purchasableId];

                    if ($this->hasStock() && !$this->hasUnlimitedStock && $lineItemQty > $this->stock) {
                        $error = Craft::t('commerce', 'There are only {num} “{description}” items left in stock.', ['num' => $this->stock, 'description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->getMinQty() > 1 && $lineItemQty < $this->getMinQty()) {
                        $error = Craft::t('commerce', 'Minimum order quantity for this item is {num}.', ['num' => $this->minQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->getMaxQty() != 0 && $lineItemQty > $this->getMaxQty()) {
                        $error = Craft::t('commerce', 'Maximum order quantity for this item is {num}.', ['num' => $this->maxQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }
                },
            ],
            [['qty'], 'integer', 'min' => 1, 'skipOnError' => false],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        return $this->getAvailableForPurchase();
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['sku'], 'string', 'max' => 255],
            [['sku', 'price'], 'required', 'on' => self::SCENARIO_LIVE],
            [['price', 'promotionalPrice', 'weight', 'width', 'length', 'height'], 'number'],
            [
                ['sku'],
                UniqueValidator::class,
                'targetClass' => PurchasableRecord::class,
                'caseInsensitive' => true,
                'on' => self::SCENARIO_LIVE,
            ],
            [
                ['stock'],
                'required',
                'when' => static function($model) {
                    /** @var Purchasable $model */
                    return !$model->hasUnlimitedStock;
                },
                'on' => self::SCENARIO_LIVE,
            ],
            [['stock'], 'number'],
            [['basePrice'], 'number'],
            [['basePromotionalPrice', 'minQty', 'maxQty'], 'number', 'skipOnEmpty' => true],
            [['freeShipping', 'hasUnlimitedStock', 'promotable', 'availableForPurchase'], 'boolean'],
            [['taxCategoryId', 'shippingCategoryId'], 'safe'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem): void
    {
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return $this->freeShipping;
    }

    public function getIsShippable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsTaxable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(?Store $store = null): bool
    {
        return $this->getPromotable($store);
    }

    /**
     * @inheritdoc
     */
    public function getPromotionRelationSource(): mixed
    {
        return $this->id;
    }

    /**
     * Update purchasable table
     *
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @throws InvalidConfigException
     * @throws InvalidConfigException
     */
    public function afterSave(bool $isNew): void
    {
        $purchasable = PurchasableRecord::findOne($this->id);

        if (!$purchasable) {
            $purchasable = new PurchasableRecord();
        }

        $purchasable->sku = $this->getSku();
        $purchasable->id = $this->id;
        $purchasable->width = $this->width;
        $purchasable->height = $this->height;
        $purchasable->length = $this->length;
        $purchasable->weight = $this->weight;
        $purchasable->taxCategoryId = $this->taxCategoryId;
        $purchasable->shippingCategoryId = $this->shippingCategoryId;

        // Only update the description for the primary site until we have a concept
        // of an order having a site ID
        if ($this->siteId == Craft::$app->getSites()->getPrimarySite()->id) {
            $purchasable->description = $this->getDescription();
        }

        $purchasable->save(false);

        // Set purchasables stores data
        if ($purchasable->id) {
            $purchasableElement = $this;
            Plugin::getInstance()->getStores()->getAllStores()->each(function($store) use ($purchasableElement) {
                $purchasableStore = PurchasableStore::findOne([
                    'purchasableId' => $purchasableElement->id,
                    'storeId' => $store->id,
                ]);
                if (!$purchasableStore) {
                    $purchasableStore = Craft::createObject(PurchasableStore::class);
                    $purchasableStore->purchasableId = $purchasableElement->id;
                    $purchasableStore->storeId = $store->id;
                }

                /** @var PurchasableStoreModel|null $ps */
                $ps = $this->getPurchasableStores()->firstWhere('storeId', $store->id);

                if (!$ps) {
                    $ps = Craft::createObject([
                        'class' => PurchasableStoreModel::class,
                        'purchasableId' => $purchasableStore->purchasableId,
                        'storeId' => $purchasableStore->storeId,
                    ]);

                    $purchasableStores = $this->getPurchasableStores();
                    $purchasableStores->add($ps);
                }

                $purchasableStore->price = $ps->price;
                $purchasableStore->promotionalPrice = $ps->promotionalPrice;
                $purchasableStore->stock = $ps->stock;
                $purchasableStore->hasUnlimitedStock = $ps->hasUnlimitedStock;
                $purchasableStore->minQty = $ps->minQty;
                $purchasableStore->maxQty = $ps->maxQty;
                $purchasableStore->promotable = $ps->promotable;
                $purchasableStore->availableForPurchase = $ps->availableForPurchase;
                $purchasableStore->freeShipping = $ps->freeShipping;

                $purchasableStore->save(false);
                $ps->id = $purchasableStore->id;
            });
        }

        parent::afterSave($isNew);
    }

    /**
     * Clean up purchasable table
     */
    public function afterDelete(): void
    {
        $purchasable = PurchasableRecord::findOne($this->id);

        $purchasable?->delete();

        parent::afterDelete();
    }

    /**
     * @return Sale[] The sales that relate directly to this purchasable
     * @throws InvalidConfigException
     */
    public function relatedSales(): array
    {
        return Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($this);
    }

    /**
     * @inheritdoc
     */
    public function getOnPromotion(?Store $store = null): bool
    {
        return $this->getPromotionalPrice($store) !== null;
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
     * @inheritdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $html = parent::metaFieldsHtml($static);

        $html .= Cp::selectFieldHtml([
            'id' => 'tax-category',
            'name' => 'taxCategoryId',
            'label' => Craft::t('commerce', 'Tax Category'),
            'options' => Plugin::getInstance()->getTaxCategories()->getAllTaxCategoriesAsList(),
            'value' => $this->taxCategoryId,
        ]);

        $html .= Cp::selectFieldHtml([
            'id' => 'shipping-category',
            'name' => 'shippingCategoryId',
            'label' => Craft::t('commerce', 'Shipping Category'),
            'options' => Plugin::getInstance()->getShippingCategories()->getAllShippingCategoriesAsList(),
            'value' => $this->shippingCategoryId,
        ]);

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'sku' => Html::encode($this->getSkuAsText()),
            'price' => $this->getBasePrice(), // @TODO change this to the `asCurrency` attribute when implemented
            'promotionalPrice' => $this->getBasePromotionalPrice(), // @TODO change this to the `asCurrency` attribute when implemented
            'weight' => $this->weight !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->weightUnits : '',
            'length' => $this->length !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            'width' => $this->width !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            'height' => $this->height !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            default => parent::tableAttributeHtml($attribute),
        };
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'title' => Craft::t('commerce', 'Title'),
            'sku' => Craft::t('commerce', 'SKU'),
            'price' => Craft::t('commerce', 'Price'),
            'width' => Craft::t('commerce', 'Width ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'height' => Craft::t('commerce', 'Height ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'length' => Craft::t('commerce', 'Length ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'weight' => Craft::t('commerce', 'Weight ({unit})', ['unit' => Plugin::getInstance()->getSettings()->weightUnits]),
            'stock' => Craft::t('commerce', 'Stock'),
            'minQty' => Craft::t('commerce', 'Quantities'),
        ]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'sku',
            'price',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce', 'Title'),
            'sku' => Craft::t('commerce', 'SKU'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [...parent::defineSearchableAttributes(), ...[
            'sku',
            'price',
            'width',
            'height',
            'length',
            'weight',
            'stock',
            'hasUnlimitedStock',
            'minQty',
            'maxQty',
        ]];
    }
}
