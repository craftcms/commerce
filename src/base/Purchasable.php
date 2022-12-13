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
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\Purchasable as PurchasableHelper;
use craft\commerce\models\LineItem;
use craft\commerce\models\PurchasableStore as PurchasableStoreModel;
use craft\commerce\models\Sale;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\Purchasable as PurchasableRecord;
use craft\commerce\records\PurchasableStore;
use craft\errors\SiteNotFoundException;
use craft\validators\UniqueValidator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Base Purchasable
 *
 * @property string $description the element's title or any additional descriptive information
 * @property bool $isAvailable whether the purchasable is currently available for purchase
 * @property bool $isPromotable whether this purchasable can be subject to discounts or sales
 * @property bool $onSale
 * @property float $promotionRelationSource The source for any promotion category relation
 * @property float $price the base price the item will be added to the line item with
 * @property-read float $salePrice the base price the item will be added to the line item with
 * @property-read string $priceAsCurrency the base price the item will be added to the line item with
 * @property-read string $salePriceAsCurrency the base price the item will be added to the line item with
 * @property-read Sale[] $sales sales models which are currently affecting the salePrice of this purchasable
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
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class Purchasable extends Element implements PurchasableInterface
{
    /**
     * @var float|null
     */
    private ?float $_salePrice = null;

    /**
     * @var Sale[]|null
     */
    private ?array $_sales = null;

    /**
     * @var Collection|null
     * @since 5.0.0
     */
    private ?Collection $_purchasableStores = null;

    /**
     * @var int|null
     * @since 5.0.0
     */
    public ?int $storeId = null;

    /**
     * @var Store|null
     * @since 5.0.0
     */
    private ?Store $_store = null;

    /**
     * @var string SKU
     * @see getSku()
     * @see setSku()
     */
    private string $_sku = '';

    /**
     * @var int|null Tax category ID
     * @see setTaxCategoryId()
     * @see getTaxCategoryId()
     * @since 5.0.0
     */
    public ?int $_taxCategoryId = null;

    /**
     * @var int|null Shipping category ID
     * @since 5.0.0
     */
    public ?int $_shippingCategoryId = null;

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
     * @var float|null $price
     * @since 5.0.0
     */
    private ?float $_price = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();

        $names[] = 'isAvailable';
        $names[] = 'isPromotable';
        $names[] = 'price';
        $names[] = 'shippingCategoryId';
        $names[] = 'sku';
        $names[] = 'taxCategoryId';
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
    public static function displayName(): string
    {
        $classNameParts = explode('\\', static::class);

        return array_pop($classNameParts);
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
     * @param string|Store|null $store
     * @return mixed
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @since 5.0.0
     */
    public function getPurchasableStoreValue(string $key, string|Store|null $store = null): mixed
    {
        if ($store === null) {
            $store = $this->getStore() ?? Craft::$app->getSites()->getCurrentSite();
        } elseif (is_string($store)) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($store);
        }

        if (!$store) {
            throw new InvalidConfigException('Invalid store.');
        }

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
     * @param string|Store|null $store
     * @return void
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @since 5.0.0
     */
    public function setPurchasableStoreValue(string $key, mixed $value = null, string|Store|null $store = null): void
    {
        if ($store === null) {
            $store = $this->getStore() ?? Craft::$app->getSites()->getCurrentSite();
        } elseif (is_string($store)) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($store);
        }

        if (!$store) {
            throw new InvalidConfigException('Invalid store.');
        }

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
     * @param int|Store|null $store
     * @return void
     * @throws \yii\base\InvalidConfigException
     * @since 5.0.0
     */
    public function setStore(int|Store|null $store): void
    {
        if ($store === null) {
            $this->storeId = null;
            $this->_store = null;
            return;
        }

        if (is_int($store)) {
            $store = Plugin::getInstance()->getStores()->getStoreById($store);
            if ($store === null) {
                throw new InvalidArgumentException('Invalid store ID: ' . $store);
            }

            $this->storeId = $store->id;
            $this->_store = $store;
        }
    }

    /**
     * @return Store|null
     * @throws \yii\base\InvalidConfigException
     * @since 5.0.0
     */
    public function getStore(): ?Store
    {
        if ($this->_store === null && $this->storeId === null) {
            return null;
        }

        if ($this->_store instanceof Store) {
            return $this->_store;
        }

        if ($this->storeId !== null) {
            $this->_store = Plugin::getInstance()->getStores()->getStoreById($this->storeId);
        }

        return $this->_store;
    }

    /**
     * @inheritdoc
     */
    public function setBasePromotionalPrice(?float $price, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('promotionalPrice', $price, $store);
    }

    /**
     * @inheritdoc
     */
    public function getBasePromotionalPrice(string|Store|null $store = null): ?float
    {
        return $this->getPurchasableStoreValue('promotionalPrice', $store);
    }

    /**
     * @inheritdoc
     */
    public function setFreeShipping(bool $freeShipping, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('freeShipping', $freeShipping, $store);
    }

    /**
     * @inheritdoc
     */
    public function getFreeShipping(string|Store|null $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('freeShipping', $store);
    }

    /**
     * @inheritdoc
     */
    public function setPromotable(bool $promotable, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('promotable', $promotable, $store);
    }

    /**
     * @inheritdoc
     */
    public function getPromotable(string|Store|null $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('promotable', $store);
    }

    /**
     * @inheritdoc
     */
    public function setAvailableForPurchase(bool $availableForPurchase, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('availableForPurchase', $availableForPurchase, $store);
    }

    /**
     * @inheritdoc
     */
    public function getAvailableForPurchase(string|Store|null $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('availableForPurchase', $store);
    }

    /**
     * @inheritdoc
     */
    public function setMinQty(?int $minQty, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('minQty', $minQty, $store);
    }

    /**
     * @inheritdoc
     */
    public function getMinQty(string|Store|null $store = null): ?int
    {
        return $this->getPurchasableStoreValue('minQty', $store);
    }

    /**
     * @inheritdoc
     */
    public function setMaxQty(?int $maxQty, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('maxQty', $maxQty, $store);
    }

    /**
     * @inheritdoc
     */
    public function getMaxQty(string|Store|null $store = null): ?int
    {
        return $this->getPurchasableStoreValue('maxQty', $store);
    }

    /**
     * @inheritdoc
     */
    public function setHasUnlimitedStock(bool $hasUnlimitedStock, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('hasUnlimitedStock', $hasUnlimitedStock, $store);
    }

    /**
     * @inheritdoc
     */
    public function getHasUnlimitedStock(string|Store|null $store = null): bool
    {
        return (bool)$this->getPurchasableStoreValue('hasUnlimitedStock', $store);
    }

    /**
     * @inheritdoc
     */
    public function getBasePrice(string|Store|null $store = null): ?float
    {
        return $this->getPurchasableStoreValue('price', $store);
    }

    /**
     * @param float|null $price
     * @param string|Store|null $store
     * @return void
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function setBasePrice(?float $price, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('price', $price, $store);
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
     * @param string|Store|null $store
     * @return float|null
     * @since 5.0.0
     */
    public function getPrice(string|Store|null $store = null): ?float
    {
        return $this->_price ?? $this->getBasePrice($store);
    }

    /**
     * @param string|Store|null $store
     * @return float|null
     * @since 5.0.0
     */
    public function getPromotionalPrice(string|Store|null $store = null): ?float
    {
        return $this->_promotionalPrice ?? $this->getBasePromotionalPrice($store);
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
    public function hasStock(string|Store|null $store = null): bool
    {
        return $this->getPurchasableStoreValue('stock', $store) > 0 || $this->getPurchasableStoreValue('hasUnlimitedStock', $store);
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategoryId(): int
    {
        return $this->_taxCategoryId ?? Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
    }

    /**
     * @param int|null $taxCategoryId
     * @return void
     */
    public function setTaxCategoryId(?int $taxCategoryId): void
    {
        $this->_taxCategoryId = $taxCategoryId;
    }

    /**
     * @inheritdoc
     */
    public function setStock(?int $stock, string|Store|null $store = null): void
    {
        $this->setPurchasableStoreValue('stock', $stock, $store);
    }

    /**
     * @param string|Store|null $store
     * @return int|null
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @since 5.0.0
     */
    public function getStock(string|Store|null $store = null): ?int
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
    public function getSalePrice(): float
    {
        $this->_loadSales();

        return $this->_salePrice;
    }

    /**
     * Returns an array of sales models which are currently affecting the salePrice of this purchasable.
     *
     * @return Sale[]|null
     */
    public function getSales(): ?array
    {
        $this->_loadSales();

        return $this->_sales;
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategoryId(): int
    {
        return $this->_shippingCategoryId ?? Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory()->id;
    }

    /**
     * @param int|null $shippingCategoryId
     * @return void
     */
    public function setShippingCategoryId(?int $shippingCategoryId): void
    {
        $this->_shippingCategoryId = $shippingCategoryId;
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
    }

    /**
     * @inheritdoc
     */
    public function getLineItemRules(LineItem $lineItem): array
    {
        return [];
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
     * Update purchasable table
     *
     * @throws SiteNotFoundException
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
        $purchasable->taxCategoryId = $this->getTaxCategoryId();
        $purchasable->shippingCategoryId = $this->getShippingCategoryId();

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

                $ps = $this->getPurchasableStores()->firstWhere('storeId', $store->id);

                if (!$ps) {
                    throw new InvalidConfigException('Invalid store');
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
     */
    public function relatedSales(): array
    {
        return Plugin::getInstance()->getSales()->getSalesRelatedToPurchasable($this);
    }

    public function getOnSale(): bool
    {
        return Currency::round($this->getSalePrice()) !== Currency::round($this->getPrice());
    }

    /**
     * Reloads any sales applicable to the purchasable for the current user.
     */
    private function _loadSales(): void
    {
        if (!isset($this->_sales)) {
            // Default the sales and salePrice to the original price without any sales
            $this->_sales = [];
            $this->_salePrice = Currency::round($this->getPrice());

            if ($this->getId()) {
                $this->_sales = Plugin::getInstance()->getSales()->getSalesForPurchasable($this);
                $this->_salePrice = Plugin::getInstance()->getSales()->getSalePriceForPurchasable($this);
            }
        }
    }
}
