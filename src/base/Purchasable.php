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
use craft\commerce\models\LineItem;
use craft\commerce\models\Sale;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\Purchasable as PurchasableRecord;
use craft\errors\SiteNotFoundException;
use craft\validators\UniqueValidator;
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
     * @var float|null
     */
    private ?float $_baseSalePrice = null;

    /**
     * @var float|null
     */
    private ?float $_basePrice = null;

    /**
     * Array of base prices indexed by store handle.
     *
     * @var array
     */
    public array $basePrices = [];

    /**
     * Array of base sale prices indexed by store handle.
     *
     * @var array
     */
    public array $baseSalePrices = [];

    /**
     * @var float|null
     */
    private ?float $_price = null;

    /**
     * @var int|null
     * @since 5.0.0
     */
    private ?int $_storeId = null;

    /**
     * @var Store|null
     * @since 5.0.0
     */
    private ?Store $_store = null;

    /**
     * @var Sale[]|null
     */
    private ?array $_sales = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();

        $names[] = 'isAvailable';
        $names[] = 'isPromotable';
        $names[] = 'price';
        $names[] = 'salePrice';
        $names[] = 'basePrice';
        $names[] = 'baseSalePrice';
        $names[] = 'basePrices';
        $names[] = 'baseSalePrices';
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
     * @inheritdoc
     */
    public function getSnapshot(): array
    {
        return [];
    }

    /**
     * @param int|null $storeId
     * @return void
     * @since 5.0.0
     */
    public function setStoreId(?int $storeId): void
    {
        $this->_storeId = $storeId;
    }

    /**
     * @return int|null
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getStoreId(): ?int
    {
        if ($this->_storeId === null && $this->_store === null) {
            return null;
        }

        return $this->getStore()?->id;
    }

    /**
     * @param int|Store|null $store
     * @return void
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function setStore(int|Store|null $store): void
    {
        if ($store === null) {
            $this->_storeId = null;
            $this->_store = null;
            return;
        }

        if (is_int($store)) {
            $store = Plugin::getInstance()->getStores()->getStoreById($store);
            if ($store === null) {
                throw new InvalidArgumentException('Invalid store ID: ' . $store);
            }

            $this->_storeId = $store->id;
            $this->_store = $store;
        }
    }

    /**
     * @return Store|null
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getStore(): ?Store
    {
        if ($this->_store === null && $this->_storeId === null) {
            return null;
        }

        if ($this->_store instanceof Store) {
            return $this->_store;
        }

        if ($this->_storeId !== null) {
            $this->_store = Plugin::getInstance()->getStores()->getStoreById($this->_storeId);
        }

        return $this->_store;
    }

    /**
     * @inheritdoc
     */
    public function getSalePrice(?string $storeHandle = null): ?float
    {
        if ($storeHandle === null) {
            $store = $this->getStore() ?? Plugin::getInstance()->getStores()->getCurrentStore();
        } else {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
        }

        if ($store === null) {
            throw new InvalidArgumentException('Invalid store handle');
        }

        $price = $this->getPrice($storeHandle);
        // Live get catalog price
        $salePrice = Plugin::getInstance()->getCatalogPricing()->getCatalogPrice($this->id, $store->id, Craft::$app->getUser()->getIdentity()?->id, true);

        return $price <= $salePrice ? null : $salePrice;
    }

    /**
     * @inheritdoc
     */
    public function getPrice(?string $storeHandle = null): ?float
    {
        if ($storeHandle === null) {
            $store = $this->getStore() ?? Plugin::getInstance()->getStores()->getCurrentStore();
        } else {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
        }

        if ($store === null) {
            throw new InvalidArgumentException('Invalid store handle');
        }

        // Live get catalog price
        $catalogPrice = Plugin::getInstance()->getCatalogPricing()->getCatalogPrice($this->id, $store->id, Craft::$app->getUser()->getIdentity()?->id, false);

        return $catalogPrice ?? $this->getBasePrice($store->handle);
    }

    public function getBaseSalePrice(?string $storeHandle = null): ?float
    {
        if ($storeHandle === null) {
            $storeHandle = Plugin::getInstance()->getStores()->getCurrentStore()->handle;
        }

        if (!isset($this->baseSalePrices[$storeHandle])) {
            return null;
        }

        return $this->baseSalePrices[$storeHandle] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function setBaseSalePrice(?float $price, string $storeHandle): void
    {
        if (!in_array($storeHandle, Plugin::getInstance()->getStores()->getAllStores()->map(fn($store) => $store->handle)->all(), true)) {
            throw new InvalidArgumentException('Invalid store handle');
        }

        $this->baseSalePrices[$storeHandle] = $price;
    }

    /**
     * @inheritdoc
     */
    public function getBasePrice(?string $storeHandle = null): ?float
    {
        if ($storeHandle === null) {
            $storeHandle = Plugin::getInstance()->getStores()->getCurrentStore()->handle;
        }

        if (!isset($this->basePrices[$storeHandle])) {
            return null;
        }

        return $this->basePrices[$storeHandle] ?? null;
    }

    public function setBasePrice(?float $price, string $storeHandle): void
    {
        if (!in_array($storeHandle, Plugin::getInstance()->getStores()->getAllStores()->map(fn($store) => $store->handle)->all(), true)) {
            throw new InvalidArgumentException('Invalid store handle');
        }

        $this->basePrices[$storeHandle] = $price;
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
    public function getTaxCategoryId(): int
    {
        return Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
    }

    /**
     * @inheritdoc
     */
    public function getShippingCategoryId(): int
    {
        return Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory()->id;
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
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [
                ['sku'],
                UniqueValidator::class,
                'targetClass' => PurchasableRecord::class,
                'caseInsensitive' => true,
                'on' => self::SCENARIO_LIVE,
            ],
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
        return false;
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
        return true;
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

        // Only update the description for the primary site until we have a concept
        // of an order having a site ID
        if ($this->siteId == Craft::$app->getSites()->getPrimarySite()->id) {
            $purchasable->description = $this->getDescription();
        }

        $purchasable->save(false);

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

    /**
     * @param string|null $storeHandle
     * @return bool
     */
    public function getOnSale(?string $storeHandle = null): bool
    {
        $salePrice = $this->getSalePrice($storeHandle);
        if ($salePrice === null) {
            return false;
        }

        return Currency::round($salePrice) !== Currency::round($this->getPrice($storeHandle));
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
