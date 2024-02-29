<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use Craft;
use craft\base\Element;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\InventoryItem;
use craft\commerce\models\InventoryLevel;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\Sale;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\commerce\records\InventoryItem as InventoryItemRecord;
use craft\commerce\records\Purchasable as PurchasableRecord;
use craft\commerce\records\PurchasableStore;
use craft\errors\DeprecationException;
use craft\errors\SiteNotFoundException;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\MoneyHelper;
use craft\validators\UniqueValidator;

use Illuminate\Support\Collection;
use Money\Money;
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
 * @property float $price the price the item will be added to the line item with
 * @property float|null $basePrice
 * @property float|null $basePromotionalPrice
 * @property-read float $salePrice the base price the item will be added to the line item with
 * @property-read string $priceAsCurrency the price
 * @property-read string $basePriceAsCurrency the base price
 * @property-read string $basePromotionalPriceAsCurrency the base promotional price
 * @property-read string $salePriceAsCurrency the base price the item will be added to the line item with
 * @property int $shippingCategoryId the purchasable's shipping category ID
 * @property string $sku a unique code as per the commerce_purchasables table
 * @property array $snapshot
 * @property bool $isShippable
 * @property bool $isTaxable
 * @property int $taxCategoryId the purchasable's tax category ID
 * @property-read Store $store
 * @property-read int $storeId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class Purchasable extends Element implements PurchasableInterface, HasStoreInterface
{
    /**
     * @var float|null
     */
    private ?float $_salePrice = null;

    /**
     * @var float|null
     * @see getPrice()
     * @see setPrice()
     */
    private ?float $_price = null;

    /**
     * @var Sale[]|null
     */
    private ?array $_sales = null;

    /**
     * Promotional price generated by the sales system.
     *
     * @var float|null
     */
    private ?float $_salesPrice = null;

    /**
     * The store based on the `siteId` of the instance of the purchasable.
     *
     * @var Store|null
     */
    private ?Store $_store = null;

    /**
     * @var float|null
     * @see getPromotionalPrice()
     * @see setPromotionalPrice()
     */
    private ?float $_promotionalPrice = null;

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
    private ?int $_taxCategoryId = null;

    /**
     * @var TaxCategory|null Tax Category
     * @since 5.0.0
     */
    private ?TaxCategory $_taxCategory = null;

    /**
     * @var int|null Shipping category ID
     * @since 5.0.0
     */
    private ?int $_shippingCategoryId = null;

    /**
     * @var ShippingCategory|null Shipping Category
     * @since 5.0.0
     */
    private ?ShippingCategory $_shippingCategory = null;

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
     * @var float|null
     * @see getBasePrice()
     * @see setBasePrice()
     * @since 5.0.0
     */
    private ?float $_basePrice = null;

    /**
     * @var float|null
     * @see getBasePromotionalPrice()
     * @see setBasePromotionalPrice()
     * @since 5.0.0
     */
    private ?float $_basePromotionalPrice = null;


    /**
     * @var bool
     * @since 5.0.0
     */
    public bool $freeShipping = false;

    /**
     * @var bool
     * @since 5.0.0
     */
    public bool $promotable = false;

    /**
     * @var bool
     * @since 5.0.0
     */
    public bool $availableForPurchase = true;

    /**
     * @var int|null
     * @since 5.0.0
     */
    public ?int $minQty = null;

    /**
     * @var int|null
     * @since 5.0.0
     */
    public ?int $maxQty = null;

    /**
     * @var int
     * @since 5.0.0
     */
    public ?int $inventoryItemId = null;

    /**
     * This is if the store cares about tracking the stock.
     *
     * @var bool
     * @since 5.0.0
     */
    public bool $inventoryTracked = false;

    /**
     * This is the cached total available stock across all inventory locations.
     *
     * @var int
     * @since 5.0.0
     */
    private ?int $_stock = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();

        $names[] = 'isAvailable';
        $names[] = 'isPromotable';
        $names[] = 'price';
        $names[] = 'promotionalPrice';
        $names[] = 'onPromotion';
        $names[] = 'salePrice';
        $names[] = 'sku';
        $names[] = 'stock';
        $names[] = 'inventoryTracked';
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
     * @return array
     */
    public function currencyAttributes(): array
    {
        return [
            'basePrice',
            'basePromotionalPrice',
            'price',
            'promotionalPrice',
            'salePrice',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function inlineAttributeInputHtml(string $attribute): string
    {
        return match ($attribute) {
            'availableForPurchase' => PurchasableHelper::availableForPurchaseInputHtml($this->availableForPurchase),
            'price' => Currency::moneyInputHtml($this->basePrice, [
                'id' => 'base-price',
                'name' => 'basePrice',
                'currency' => $this->getStore()->getCurrency()->getCode(),
                'currencyLabel' => $this->getStore()->getCurrency()->getCode(),
            ]),
            'promotionalPrice' => Currency::moneyInputHtml($this->basePromotionalPrice, [
                'id' => 'base-promotional-price',
                'name' => 'basePromotionalPrice',
                'currency' => $this->getStore()->getCurrency()->getCode(),
                'currencyLabel' => $this->getStore()->getCurrency()->getCode(),
            ]),
            'sku' => PurchasableHelper::skuInputHtml($this->getSkuAsText()),
            default => parent::inlineAttributeInputHtml($attribute),
        };
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

            $this->_store = Plugin::getInstance()->getStores()->getStoreBySiteId($this->siteId);
            if ($this->_store === null) {
                throw new InvalidConfigException('Unable to retrieve store.');
            }
        }

        return $this->_store;
    }

    /**
     * @return int
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getStoreId(): int
    {
        return $this->getStore()->id;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function getIsAvailable(): bool
    {
        // Is the element available for purchase?
        if (!$this->availableForPurchase) {
            return false;
        }

        // is the element enabled?
        if ($this->getStatus() !== Element::STATUS_ENABLED) {
            return false;
        }

        // Is the inventory tracked and is there stock?
        if ($this->inventoryTracked && $this->getStock() < 1) {
            return false;
        }

        // Temporary SKU can not be added to the cart
        if (PurchasableHelper::isTempSku($this->getSku())) {
            return false;
        }

        return true;
    }

    /**
     * @param Money|array|float|int|null $basePrice
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function setBasePrice(Money|array|float|int|null $basePrice): void
    {
        if (is_array($basePrice)) {
            if (isset($basePrice['value']) && $basePrice['value'] === '') {
                $this->_basePrice = null;
                return;
            }

            if (!isset($basePrice['currency'])) {
                $basePrice['currency'] = $this->getStore()->getCurrency();
            }

            $basePrice = MoneyHelper::toMoney($basePrice);
            // nullify if conversion fails
            $basePrice = $basePrice ?: null;
        }

        if ($basePrice instanceof Money) {
            $basePrice = MoneyHelper::toDecimal($basePrice);
        } elseif ($basePrice !== null) {
            $basePrice = (float)$basePrice;
        }

        $this->_basePrice = $basePrice;
    }

    /**
     * @return float|null
     * @since 5.0.0
     */
    public function getBasePrice(): ?float
    {
        return $this->_basePrice;
    }

    /**
     * @param Money|array|float|int|null $basePromotionalPrice
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function setBasePromotionalPrice(Money|array|float|int|null $basePromotionalPrice): void
    {
        if (is_array($basePromotionalPrice)) {
            if (isset($basePromotionalPrice['value']) && $basePromotionalPrice['value'] === '') {
                $this->_basePromotionalPrice = null;
                return;
            }

            if (!isset($basePromotionalPrice['currency'])) {
                $basePromotionalPrice['currency'] = $this->getStore()->getCurrency();
            }

            $basePromotionalPrice = MoneyHelper::toMoney($basePromotionalPrice);
            // nullify if conversion fails
            $basePromotionalPrice = $basePromotionalPrice ?: null;
        }

        if ($basePromotionalPrice instanceof Money) {
            $basePromotionalPrice = MoneyHelper::toDecimal($basePromotionalPrice);
        } elseif ($basePromotionalPrice !== null) {
            $basePromotionalPrice = (float)$basePromotionalPrice;
        }

        $this->_basePromotionalPrice = $basePromotionalPrice;
    }

    /**
     * @return float|null
     * @since 5.0.0
     */
    public function getBasePromotionalPrice(): ?float
    {
        return $this->_basePromotionalPrice;
    }

    /**
     * @param float|null $price
     * @return void
     * @since 5.0.0
     */
    public function setPrice(?float $price): void
    {
        $this->_price = $price;
    }

    /**
     * @return float|null
     * @throws InvalidConfigException
     * @throws \Throwable
     */
    public function getPrice(): ?float
    {
        if (!Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules()) {
            return $this->basePrice;
        }

        $price = $this->_price ?? $this->basePrice;

        $price = MoneyHelper::toMoney([
            'value' => $price,
            'currency' => $this->getStore()->getCurrency(),
        ]);

        return (float)MoneyHelper::toDecimal($price);
    }

    /**
     * @return float|null
     * @throws InvalidConfigException
     * @throws \Throwable
     * @since 5.0.0
     */
    public function getPromotionalPrice(): ?float
    {
        $price = $this->getPrice();
        if (!Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules()) {
            // Use the sales system to figure out the price
            $this->_loadSales();
            $promotionalPrice = $this->_salesPrice ?? $this->basePromotionalPrice;
        } else {
            $promotionalPrice = $this->_promotionalPrice ?? $this->basePromotionalPrice;
        }

        return ($promotionalPrice !== null && $promotionalPrice < $price) ? $promotionalPrice : null;
    }

    /**
     * @param float|null $price
     * @return void
     * @since 5.0.0
     */
    public function setPromotionalPrice(?float $price): void
    {
        $this->_promotionalPrice = $price;
    }

    /**
     * @inheritdoc
     */
    public function getSalePrice(): ?float
    {
        if ($this->_salePrice === null) {
            $this->_salePrice = $this->getPromotionalPrice() ?? $this->getPrice();
        }

        return $this->_salePrice ?? null;
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
    public function hasStock(): bool
    {
        return !$this->inventoryTracked || $this->getStock() > 0;
    }

    /**
     * @param int|null $taxCategoryId
     * @return void
     * @since 5.0.0
     */
    public function setTaxCategoryId(?int $taxCategoryId = null): void
    {
        $this->_taxCategoryId = $taxCategoryId;
    }

    /**
     * @return int
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getTaxCategoryId(): int
    {
        if ($this->_taxCategoryId === null) {
            $this->_taxCategoryId = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
        }

        return $this->_taxCategoryId;
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategory(): TaxCategory
    {
        if ($this->_taxCategory === null || $this->_taxCategory->id != $this->getTaxCategoryId()) {
            $this->_taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->getTaxCategoryId());
        }

        return $this->_taxCategory;
    }

    /**
     * @inheritdoc
     */
    public function getSnapshot(): array
    {
        return [];
    }

    /**
     * @param int|null $shippingCategoryId
     * @return void
     * @since 5.0.0
     */
    public function setShippingCategoryId(?int $shippingCategoryId = null): void
    {
        $this->_shippingCategoryId = $shippingCategoryId;
    }

    /**
     * @return int
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getShippingCategoryId(): int
    {
        if ($this->_shippingCategoryId === null) {
            $this->_shippingCategoryId = Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($this->getStoreId())->id;
        }

        return $this->_shippingCategoryId;
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategory(): ShippingCategory
    {
        if ($this->_shippingCategory === null || $this->_shippingCategory->id !== $this->getShippingCategoryId()) {
            $this->_shippingCategory = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->getShippingCategoryId(), $this->getStoreId());
        }

        return $this->_shippingCategory;
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
            if ($this->inventoryTracked && ($lineItem->qty > $this->getStock())) {
                $message = Craft::t('commerce', '{description} only has {stock} in stock.', ['description' => $lineItem->getDescription(), 'stock' => $this->getStock()]);
                /** @var OrderNotice $notice */
                $notice = Craft::createObject([
                    'class' => OrderNotice::class,
                    'attributes' => [
                        'type' => 'lineItemMaxStockReached',
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
                    if (!$this->hasStock()) {
                        $error = Craft::t('commerce', '“{description}” is currently out of stock.', ['description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    $lineItemQty = $lineItem->id !== null ? $lineItemQuantitiesById[$lineItem->id] : $lineItemQuantitiesByPurchasableId[$lineItem->purchasableId];

                    if ($this->hasStock() && $this->inventoryTracked && $lineItemQty > $this->getStock()) {
                        $error = Craft::t('commerce', 'There are only {num} “{description}” items left in stock.', ['num' => $this->getStock(), 'description' => $lineItem->purchasable->getDescription()]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->minQty > 1 && $lineItemQty < $this->minQty) {
                        $error = Craft::t('commerce', 'Minimum order quantity for this item is {num}.', ['num' => $this->minQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }

                    if ($this->maxQty != 0 && $lineItemQty > $this->maxQty) {
                        $error = Craft::t('commerce', 'Maximum order quantity for this item is {num}.', ['num' => $this->maxQty]);
                        $validator->addError($lineItem, $attribute, $error);
                    }
                },
            ],
        ];
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
            [['basePrice'], 'number'],
            [['basePromotionalPrice', 'minQty', 'maxQty'], 'number', 'skipOnEmpty' => true],
            [['freeShipping', 'inventoryTracked', 'promotable', 'availableForPurchase'], 'boolean'],
            [['taxCategoryId', 'shippingCategoryId', 'price', 'promotionalPrice', 'productSlug', 'productTypeHandle'], 'safe'],
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
    public function getIsPromotable(): bool
    {
        return $this->promotable;
    }

    /**
     * @inheritdoc
     */
    public function getPromotionRelationSource(): mixed
    {
        return $this->id;
    }

    /**
     * @return InventoryItem
     * @throws InvalidConfigException
     */
    public function getInventoryItem(): InventoryItem
    {
        return Plugin::getInstance()->getInventory()->getInventoryItemByPurchasable($this);
    }

    /**
     * @deprecated in 5.0.0 use [[Purchasable::$inventoryTracked]] instead.
     */
    public function getHasUnlimitedStock(): bool
    {
        return !$this->inventoryTracked;
    }

    /**
     * @deprecated in 5.0.0 use [[Purchasable::$inventoryTracked]] instead.
     */
    public function setHasUnlimitedStock($value): bool
    {
        return $this->inventoryTracked = !$value;
    }

    /**
     * @return int
     */
    private function _getStock(): int
    {
        $saleableAmount = 0;
        foreach ($this->getInventoryLevels() as $inventoryLevel) {
            if ($inventoryLevel->availableTotal > 0) {
                $saleableAmount += $inventoryLevel->availableTotal;
            }
        }

        return $saleableAmount;
    }

    /**
     * Returns the cached total available stock across all inventory locations for this store.
     *
     * @return int
     */
    public function getStock(): int
    {
        if ($this->_stock === null) {
            $this->_stock = $this->_getStock();
        }

        return $this->_stock;
    }


    /**
     * Returns the total stock across all locations this purchasable is tracked in.
     * @return Collection<InventoryLevel>
     * @since 5.0.0
     */
    public function getInventoryLevels(): Collection
    {
        return Plugin::getInstance()->getInventory()->getInventoryLevelsForPurchasable($this);
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
        if (!$this->propagating) {
            $purchasableId = $this->getCanonicalId();

            $purchasable = PurchasableRecord::findOne($purchasableId);

            if (!$purchasable) {
                $purchasable = new PurchasableRecord();
            }
            $purchasable->sku = $this->getSku();
            $purchasable->id = $purchasableId;
            $purchasable->width = $this->width;
            $purchasable->height = $this->height;
            $purchasable->length = $this->length;
            $purchasable->weight = $this->weight;
            $purchasable->taxCategoryId = $this->taxCategoryId;

            // Only update the description for the primary site until we have a concept
            // of an order having a site ID
            if ($this->siteId == Craft::$app->getSites()->getPrimarySite()->id) {
                $purchasable->description = $this->getDescription();
            }

            $purchasable->save(false);

            if ($purchasableId) {

                // Set Purchasables stores data
                $purchasableStoreRecord = PurchasableStore::findOne([
                    'purchasableId' => $purchasableId,
                    'storeId' => $this->getStoreId(),
                ]);
                if (!$purchasableStoreRecord) {
                    $purchasableStoreRecord = Craft::createObject(PurchasableStore::class);
                    $purchasableStoreRecord->storeId = $this->getStore()->id;
                }

                $purchasableStoreRecord->basePrice = $this->basePrice;
                $purchasableStoreRecord->basePromotionalPrice = $this->basePromotionalPrice;
                $purchasableStoreRecord->stock = Plugin::getInstance()->getInventory()->getInventoryLevelsForPurchasable($this)->sum('availableTotal');
                $purchasableStoreRecord->inventoryTracked = $this->inventoryTracked;
                $purchasableStoreRecord->minQty = $this->minQty;
                $purchasableStoreRecord->maxQty = $this->maxQty;
                $purchasableStoreRecord->promotable = $this->promotable;
                $purchasableStoreRecord->availableForPurchase = $this->availableForPurchase;
                $purchasableStoreRecord->freeShipping = $this->freeShipping;
                $purchasableStoreRecord->purchasableId = $purchasableId;
                $purchasableStoreRecord->shippingCategoryId = $this->getShippingCategoryId();

                $purchasableStoreRecord->save(false);

                // Only update the description for the primary site until we have a concept
                // of an order having a site ID
                if ($this->siteId == Craft::$app->getSites()->getPrimarySite()->id) {
                    $purchasable->description = $this->getDescription();
                }

                Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
                    'purchasableIds' => [$purchasableId],
                    'storeId' => $this->getStoreId(),
                ]);

                // Set the inventory item data
                $inventoryItem = InventoryItemRecord::find()->where(['purchasableId' => $purchasableId])->one();
                if (!$inventoryItem) {
                    $inventoryItem = new InventoryItemRecord();
                    $inventoryItem->purchasableId = $purchasableId;
                    $inventoryItem->countryCodeOfOrigin = '';
                    $inventoryItem->administrativeAreaCodeOfOrigin = '';
                    $inventoryItem->harmonizedSystemCode = '';
                }

                $inventoryItem->save();
            }
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
    public function getOnPromotion(): bool
    {
        return $this->getPromotionalPrice() !== null;
    }

    /**
     * @return bool
     * @throws DeprecationException
     */
    public function getOnSale(): bool
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Purchasable `' . __METHOD__ . '()` method has been deprecated. Use `getOnPromotion()` instead.');
        return $this->getOnPromotion();
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
            'options' => Plugin::getInstance()->getShippingCategories()->getAllShippingCategoriesAsList($this->getStore()->id),
            'value' => $this->shippingCategoryId,
        ]);

        return $html;
    }

    /**
     * @inheritdoc
     */
    protected function attributeHtml(string $attribute): string
    {
        $stock = '';
        if ($attribute == 'stock') {
            if (!$this->inventoryTracked) {
                $stock = '∞';
            } else {
                $stock = $this->getStock();
            }
        }

        return match ($attribute) {
            'sku' => (string)Html::encode($this->getSkuAsText()),
            'price' => (string)$this->basePriceAsCurrency, // @TODO change this to the `asCurrency` attribute when implemented
            'promotionalPrice' => (string)$this->basePromotionalPrice, // @TODO change this to the `asCurrency` attribute when implemented
            'weight' => $this->weight !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->weightUnits : '',
            'length' => $this->length !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            'width' => $this->width !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            'height' => $this->height !== null ? Craft::$app->getLocale()->getFormatter()->asDecimal($this->$attribute) . ' ' . Plugin::getInstance()->getSettings()->dimensionUnits : '',
            'minQty' => (string)$this->minQty,
            'maxQty' => (string)$this->maxQty,
            'stock' => $stock,
            default => parent::attributeHtml($attribute),
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
            'promotionalPrice' => Craft::t('commerce', 'Promotional Price'),
            'width' => Craft::t('commerce', 'Width ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'height' => Craft::t('commerce', 'Height ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'length' => Craft::t('commerce', 'Length ({unit})', ['unit' => Plugin::getInstance()->getSettings()->dimensionUnits]),
            'weight' => Craft::t('commerce', 'Weight ({unit})', ['unit' => Plugin::getInstance()->getSettings()->weightUnits]),
            'stock' => Craft::t('commerce', 'Stock'),
            'minQty' => Craft::t('commerce', 'Min Qty'),
            'maxQty' => Craft::t('commerce', 'Max Qty'),
            'availableForPurchase' => Craft::t('commerce', 'Available for purchase'),
            'inventoryTracked' => Craft::t('commerce', 'Inventory Tracked'),
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
            'description',
            'sku',
            'price',
            'width',
            'height',
            'length',
            'weight',
            'minQty',
            'maxQty',
        ]];
    }

    /**
     * Reloads any sales applicable to the purchasable for the current user.
     */
    private function _loadSales(): void
    {
        if (!isset($this->_sales)) {
            // Default the sales and salePrice to the original price without any sales
            $this->_sales = [];

            if ($this->getId()) {
                $this->_sales = Plugin::getInstance()->getSales()->getSalesForPurchasable($this);
                $this->_salesPrice = Plugin::getInstance()->getSales()->getSalePriceForPurchasable($this);
            }
        }
    }
}
