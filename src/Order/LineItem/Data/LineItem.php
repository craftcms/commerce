<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\LineItem\Data;

use Closure;
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\Plugin;
use CraftCms\Commerce\Tax\Records\TaxRate as TaxRateRecord;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Helpers\LineItem as LineItemHelper;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\LineItemEvent;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\Models\LineItemStatus;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Services\LineItemStatuses;
use CraftCms\Commerce\Services\Orders;
use CraftCms\Commerce\Services\Purchasables;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Shipping\Models\ShippingCategory;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use CraftCms\Commerce\Store\Models\Store;
use CraftCms\Commerce\Tax\Models\TaxCategory;
use DateTime;
use LitEmoji\LitEmoji;
use Money\Teller;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Line item business object — the rich domain object the rest of the codebase interacts with.
 *
 * This is a `Component`, not an Eloquent model, so bare property access (`$lineItem->price`) routes
 * through the matching `getPrice()`/`setPrice()` methods exactly like it did on the legacy Yii2
 * `Model` — this matters a great deal here, since a lot of still-legacy code (adjusters) and
 * already-migrated code (`Order::recalculate()`) reads computed values like `$lineItem->salePrice`
 * as a bare property. An Eloquent model would NOT route bare property access through a same-named
 * `getSalePrice()` method (Eloquent's `__get()` only consults real attributes/casts/accessors), so
 * unlike {@see Order} this is deliberately NOT a unified Eloquent class — persistence is handled by
 * the separate, thin {@see \CraftCms\Commerce\Order\LineItem\Models\LineItem} Eloquent model instead,
 * mirroring the `Entry\Data\EntryType` / `Entry\Models\EntryType` split in cms-6.
 */
class LineItem extends Component implements HasStoreInterface
{
    public ?int $id = null;

    public LineItemType $type = LineItemType::Purchasable;

    public float $weight = 0;

    public float $length = 0;

    public float $height = 0;

    public float $width = 0;

    public int $qty = 1;

    public string $note = '';

    public string $privateNote = '';

    public ?int $purchasableId = null;

    public ?int $orderId = null;

    public ?int $lineItemStatusId = null;

    public ?int $taxCategoryId = null;

    public ?int $shippingCategoryId = null;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    public ?string $uid = null;

    private ?string $_description = null;

    private float $_price = 0;

    private ?float $_promotionalPrice = null;

    private ?float $_salePrice = null;

    private ?array $_snapshot = null;

    private ?string $_sku = null;

    private array $_options = [];

    private ?PurchasableInterface $_purchasable = null;

    private ?Order $_order = null;

    private ?LineItemStatus $_lineItemStatus = null;

    private ?bool $_isPromotable = null;

    private ?bool $_hasFreeShipping = null;

    private ?bool $_isTaxable = null;

    private ?bool $_isShippable = null;

    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->note = LitEmoji::shortcodeToUnicode($this->note);
        $this->privateNote = LitEmoji::shortcodeToUnicode($this->privateNote);
    }

    /**
     * @throws StoreNotFoundException
     */
    public function getStore(): Store
    {
        if (!$this->getOrder()) {
            throw new StoreNotFoundException('Cannot determine line item store without an order assigned to the line item.');
        }

        return $this->getOrder()->getStore();
    }

    public function getOrder(): ?Order
    {
        if ($this->_order === null && $this->orderId) {
            $this->_order = app(Orders::class)->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    public function setOrder(Order $order): void
    {
        $this->orderId = $order->id;
        $this->_order = $order;
    }

    public function getLineItemStatus(): ?LineItemStatus
    {
        if ($this->_lineItemStatus === null && $this->lineItemStatusId) {
            $this->_lineItemStatus = app(LineItemStatuses::class)->getLineItemStatusById($this->lineItemStatusId, $this->getOrder()?->getStore()->id);
        }

        return $this->_lineItemStatus;
    }

    public function setLineItemStatus(?LineItemStatus $status = null): void
    {
        if ($status !== null) {
            $this->_lineItemStatus = $status;
            $this->lineItemStatusId = (int)$status->id;
        } else {
            $this->lineItemStatusId = null;
            $this->_lineItemStatus = null;
        }
    }

    public function getOptions(): array
    {
        return $this->_options;
    }

    public function setOptions(array|string $options): void
    {
        $options = Json::decodeIfJson($options);

        if (!is_array($options)) {
            $options = [];
        }

        // @TODO Normalize emoji handling in options to a consistent shape across DB drivers (currently only stripped when MB4 is unsupported); breaking change targeted for Commerce 6.0 #COM-46
        $this->_options = $options;
    }

    public function getSnapshot(): array
    {
        return $this->_snapshot ?? [];
    }

    public function setSnapshot(array|string $snapshot): void
    {
        $snapshot = Json::decodeIfJson($snapshot);

        if (!is_array($snapshot)) {
            $snapshot = [];
        }

        $this->_snapshot = $snapshot;
    }

    public function getDescription(): string
    {
        if ($this->_description === null || $this->_description === '') {
            return (string)($this->getSnapshot()['description'] ?? '');
        }

        return $this->_description;
    }

    public function setDescription(?string $description): void
    {
        $this->_description = (string)$description;
    }

    public function getSku(): string
    {
        if ($this->_sku === null) {
            return (string)($this->getSnapshot()['sku'] ?? '');
        }

        return $this->_sku;
    }

    public function setSku(?string $sku): void
    {
        $this->_sku = (string)$sku;
    }

    /**
     * Returns a unique hash of the line item options.
     */
    public function getOptionsSignature(): string
    {
        $lineItemId = $this->getOrder()?->isCompleted ? $this->id : null;

        return LineItemHelper::generateOptionsSignature($this->getOptions(), $lineItemId);
    }

    public function getPrice(): float
    {
        return Currency::round($this->_price);
    }

    public function setPrice(float|int $price): void
    {
        $this->_price = (float)$price;
        // clear sale price cache
        $this->_salePrice = null;
    }

    public function getPromotionalPrice(): ?float
    {
        if ($this->_promotionalPrice === null) {
            return null;
        }

        return Currency::round($this->_promotionalPrice);
    }

    public function setPromotionalPrice(float|int|null $price): void
    {
        $this->_promotionalPrice = $price !== null ? (float)$price : null;
        // clear sale price cache
        $this->_salePrice = null;
    }

    public function getSalePrice(): float
    {
        if ($this->_salePrice === null) {
            $this->_salePrice = $this->getOnPromotion() ? $this->getPromotionalPrice() : $this->getPrice();
        }

        return $this->_salePrice;
    }

    public function getPromotionalAmount(): float
    {
        if ($this->getPromotionalPrice() === null) {
            return 0;
        }

        return Currency::round($this->getPrice() - $this->getPromotionalPrice());
    }

    /**
     * Returns legacy-shaped validation rules for this line item, merging in any purchasable-supplied
     * rules from {@see PurchasableInterface::getLineItemRules()}.
     *
     * @TODO Not yet wired up to a real validator. This is kept as a plain data method, in the same
     * shape as the legacy `defineRules()`, pending the broader migration of line item validation onto
     * the new Ruleset system.
     */
    public function getValidationRules(): array
    {
        $rules = [
            [
                [
                    'optionsSignature',
                    'price',
                    'promotionalAmount',
                    'weight',
                    'length',
                    'height',
                    'width',
                    'qty',
                    'taxCategoryId',
                    'type',
                    'shippingCategoryId',
                ], 'required',
            ],
            [['snapshot'], 'required', 'when' => fn() => $this->type === LineItemType::Purchasable],
            [['qty'], 'integer', 'min' => 1],
            [['shippingCategoryId', 'taxCategoryId'], 'integer'],
            [['price'], 'number', 'min' => 0],
            [['promotionalPrice'], 'number', 'min' => 0, 'skipOnEmpty' => true],
            [['orderId', 'purchasableId', 'hasFreeShipping', 'isPromotable', 'isShippable', 'isTaxable', 'type'], 'safe'],
        ];

        if ($this->type === LineItemType::Purchasable && $this->purchasableId) {
            $order = $this->getOrder();
            $purchasable = app(Purchasables::class)->getPurchasableById($this->purchasableId, $order?->orderSiteId, $order?->getCustomer()?->id);
            if ($purchasable && !empty($purchasableRules = $purchasable->getLineItemRules($this))) {
                foreach ($purchasableRules as $rule) {
                    $rules[] = $this->_normalizePurchasableRule($rule, $purchasable);
                }
            }
        }

        // @TODO Add a validation rule preventing qty from being reduced below the total fulfilled quantity across inventory locations when the order is complete

        return $rules;
    }

    public function getFulfilledTotalQuantity(): int
    {
        if ($order = $this->getOrder()) {
            return (int)app(Inventory::class)->getInventoryFulfillmentLevels($order)
                ->filter(fn($fulfillment) => $fulfillment->getLineItem()->id === $this->id)
                ->sum('fulfilledQuantity');
        }

        return 0;
    }

    /**
     * Normalizes a purchasable's validation rule.
     */
    private function _normalizePurchasableRule(mixed $rule, PurchasableInterface $purchasable): mixed
    {
        if (isset($rule[1]) && $rule[1] instanceof Closure) {
            $method = $rule[1];
            $method = $method->bindTo($purchasable);
            $rule[1] = static function($attribute, $params, $validator, $current) use ($method) {
                $method($attribute, $params, $validator, $current);
            };
        }

        return $rule;
    }

    /**
     * The attributes on the line item that should be made available as formatted currency.
     */
    public function currencyAttributes(): array
    {
        return [
            'price',
            'promotionalPrice',
            'promotionalAmount',
            'salePrice',
            'subtotal',
            'total',
            'discount',
            'shippingCost',
            'tax',
            'taxIncluded',
            'adjustmentsTotal',
        ];
    }

    /**
     * Mirrors {@see Order::_currencyAttributeAsCurrency()}. `CurrencyAttributeBehavior` (the legacy
     * behaviour this replaces) resolved the default currency via the owner's `getStore()->getCurrency()`
     * whenever the owner implemented `HasStoreInterface`, which `LineItem` does.
     */
    private function _currencyAttributeAsCurrency(float $amount): string
    {
        return Currency::formatAsCurrency($amount, $this->getStore()->getCurrency());
    }

    public function getPriceAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getPrice());
    }

    public function getPromotionalPriceAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getPromotionalPrice() ?? 0);
    }

    public function getPromotionalAmountAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getPromotionalAmount());
    }

    public function getSalePriceAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getSalePrice());
    }

    public function getSubtotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getSubtotal());
    }

    public function getTotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getTotal());
    }

    public function getDiscountAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getDiscount());
    }

    public function getShippingCostAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getShippingCost());
    }

    public function getTaxAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getTax());
    }

    public function getTaxIncludedAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getTaxIncluded());
    }

    public function getAdjustmentsTotalAsCurrency(): string
    {
        return $this->_currencyAttributeAsCurrency($this->getAdjustmentsTotal());
    }

    public function getSubtotal(): float
    {
        return Currency::round($this->qty * $this->getSalePrice());
    }

    /**
     * Returns the Purchasable's sale price multiplied by the quantity of the line item, plus any adjustment belonging to this lineitem.
     */
    public function getTotal(): float
    {
        return (float)$this->getOrder()->getTeller()->add($this->getSubtotal(), $this->getAdjustmentsTotal());
    }

    public function getTaxableSubtotal(string $taxable): float
    {
        return match ($taxable) {
            TaxRateRecord::TAXABLE_SHIPPING => $this->getShippingCost(),
            TaxRateRecord::TAXABLE_PRICE_SHIPPING => (float)$this->getOrder()->getTeller()->sum($this->getSubtotal(), $this->getDiscount(), $this->getShippingCost()),
            default => (float)$this->getOrder()->getTeller()->add($this->getSubtotal(), $this->getDiscount()), // TaxRateRecord::TAXABLE_PRICE is default
        };
    }

    public function refresh(): bool
    {
        if ($this->type === LineItemType::Custom) {
            return true;
        }

        return $this->_refreshFromPurchasable();
    }

    /**
     * @return bool False when no related purchasable exists
     */
    private function _refreshFromPurchasable(): bool
    {
        if ($this->type === LineItemType::Custom) {
            throw new Exception('Cannot refresh a custom line item from a purchasable');
        }

        if ($this->qty <= 0 && $this->id) {
            return false;
        }

        $purchasable = $this->getPurchasable();
        if (!$purchasable || !app(Purchasables::class)->isPurchasableAvailable($purchasable, $this->getOrder())) {
            return false;
        }

        $this->_populateFromPurchasable($purchasable);

        return true;
    }

    public function setHasFreeShipping(?bool $hasFreeShipping): void
    {
        $this->_hasFreeShipping = $hasFreeShipping;
    }

    public function getHasFreeShipping(): bool
    {
        // For purchasable line item types try and get the live data
        if ($this->type === LineItemType::Purchasable && $this->getPurchasable()) {
            return $this->getPurchasable()->hasFreeShipping();
        }

        return $this->_hasFreeShipping ?? false;
    }

    public function getPurchasable(): ?PurchasableInterface
    {
        if ($this->type === LineItemType::Custom) {
            throw new InvalidConfigException('Cannot get a purchasable for a custom line item');
        }

        if (!isset($this->_purchasable) && isset($this->purchasableId)) {
            $order = $this->getOrder();
            $purchasable = app(Purchasables::class)->getPurchasableById($this->purchasableId, $order?->orderSiteId, $order?->getCustomer()?->id);

            // If we are still using sales we need to make sure that the promotional price is set.
            if (!app(CatalogPricingRules::class)->canUseCatalogPricingRules()) {
                if ($purchasable instanceof Purchasable) {
                    $purchasable->loadSales($this->getOrder());
                }
            }

            $this->_purchasable = $purchasable;
        }

        return $this->_purchasable;
    }

    public function setPurchasable(PurchasableInterface $purchasable): void
    {
        $this->purchasableId = $purchasable->getId();
        $this->_purchasable = $purchasable;
        $this->type = LineItemType::Purchasable;
    }

    public function populate(mixed $data = null): void
    {
        if ($this->type === LineItemType::Custom) {
            return;
        }

        if ($data) {
            $this->_populateFromPurchasable($data);
        }
    }

    private function _populateFromPurchasable(PurchasableInterface $purchasable): void
    {
        if ($this->type === LineItemType::Custom) {
            throw new Exception('Cannot populate a custom line item from a purchasable');
        }

        // Set all things from the purchasable interface that are applicable to the line item.
        $this->purchasableId = $purchasable->getId();
        $this->setPrice($purchasable->getPrice());
        $this->setPromotionalPrice($purchasable->getPromotionalPrice());
        $this->taxCategoryId = $purchasable->getTaxCategory()->id;
        $this->shippingCategoryId = $purchasable->getShippingCategory()->id;
        $this->setSku($purchasable->getSku());
        $this->setDescription($purchasable->getDescription());

        // Check to see if there is a discount applied that ignores promotions for this line item
        $ignorePromotions = false;
        foreach (app(Discounts::class)->getAllActiveDiscounts($this->getOrder()) as $discount) {
            if (app(Discounts::class)->matchLineItem($this, $discount, true)) {
                // Break if matched discount is set to ignore promotions.
                $ignorePromotions = $discount->ignorePromotions;
                if ($ignorePromotions) {
                    break;
                }

                // Break if matched discount is set to not apply any subsequent discounts.
                if ($discount->stopProcessing) {
                    break;
                }
            }
        }

        // One of the matching discounts has ignored promotions, so we want to remove any promotional price.
        if ($ignorePromotions) {
            $this->setPromotionalPrice(null);
        }

        $snapshot = [
            // @TODO Move these common snapshot fields (price, sku, description, purchasableId, cpEditUrl, options) into the base purchasable's getSnapshot() in Commerce 6.0
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getId(),
            'cpEditUrl' => '#',
            'options' => $this->getOptions(),
            // Only add sales information to the snapshot if we are not ignoring promotions and they are still using the sales system.
            'sales' => $ignorePromotions || app(CatalogPricingRules::class)->canUseCatalogPricingRules() ? [] : app(Sales::class)->getSalesForPurchasable($purchasable, $this->getOrder()),
        ];

        // Add our purchasable data to the snapshot, save our sales.
        $purchasableSnapshot = $purchasable->getSnapshot();
        $this->setSnapshot(array_merge($purchasableSnapshot, $snapshot));

        $purchasable->populateLineItem($this);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        $lineItemsService = Plugin::getInstance()->getLineItems();

        if ($lineItemsService->hasEventHandlers($lineItemsService::EVENT_POPULATE_LINE_ITEM)) {
            $event = new LineItemEvent(
                lineItem: $this,
                isNew: !$this->id,
            );
            $lineItemsService->trigger($lineItemsService::EVENT_POPULATE_LINE_ITEM, $event);
        }
    }

    public function setIsPromotable(?bool $isPromotable): void
    {
        $this->_isPromotable = $isPromotable;
    }

    public function getIsPromotable(): bool
    {
        // For purchasable line item types try and get the live data
        if ($this->type === LineItemType::Purchasable && $this->getPurchasable()) {
            return $this->getPurchasable()->getIsPromotable();
        }

        return $this->_isPromotable ?? false;
    }

    public function getOnPromotion(): bool
    {
        return $this->getPromotionalAmount() > 0;
    }

    public function getTaxCategory(): TaxCategory
    {
        // Category may have been archived
        $categories = app(TaxCategories::class)->getAllTaxCategories(true);
        return collect($categories)->firstWhere('id', $this->taxCategoryId);
    }

    /**
     * @throws StoreNotFoundException
     */
    public function getShippingCategory(): ShippingCategory
    {
        if (!isset($this->shippingCategoryId)) {
            throw new InvalidConfigException('Line Item is missing its shipping category ID');
        }

        // Category may have been archived
        $categories = app(ShippingCategories::class)->getAllShippingCategories(withTrashed: true);
        return $categories->firstWhere('id', $this->shippingCategoryId);
    }

    /**
     * @return \CraftCms\Commerce\Order\Models\OrderAdjustment[]
     */
    public function getAdjustments(): array
    {
        $lineItemAdjustments = [];

        $adjustments = $this->getOrder()->getAdjustments();

        foreach ($adjustments as $adjustment) {
            // Since the line item may not yet be saved and won't have an ID, we need to check the adjuster references this as it's line item.
            if (($adjustment->lineItemId && $adjustment->lineItemId == $this->id) || (!$adjustment->lineItemId && $adjustment->getLineItem() === $this)) {
                $lineItemAdjustments[] = $adjustment;
            }
        }

        return $lineItemAdjustments;
    }

    public function getAdjustmentsTotal(bool $included = false): float
    {
        $amount = 0.0;
        $teller = $this->_getTeller();
        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included == $included) {
                $amount = (float)$teller->add($amount, $adjustment->amount);
            }
        }

        return $amount;
    }

    private function _getAdjustmentsTotalByType(string $type, bool $included = false): float
    {
        $amount = 0.0;
        $teller = $this->_getTeller();
        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included == $included && $adjustment->type === $type) {
                $amount = (float)$teller->add($amount, $adjustment->amount);
            }
        }

        return $amount;
    }

    public function setIsTaxable(?bool $isTaxable): void
    {
        $this->_isTaxable = $isTaxable;
    }

    public function getIsTaxable(): bool
    {
        if ($this->type === LineItemType::Custom) {
            return $this->_isTaxable ?? false;
        }

        if (!$this->getPurchasable()) {
            return $this->_isTaxable ?? true; // we have a default tax category so assume so.
        }

        return $this->getPurchasable()->getIsTaxable();
    }

    public function setIsShippable(?bool $isShippable): void
    {
        $this->_isShippable = $isShippable;
    }

    public function getIsShippable(): bool
    {
        if ($this->type === LineItemType::Custom) {
            return $this->_isShippable ?? false;
        }

        if (!$this->getPurchasable()) {
            return $this->_isShippable ?? true; // we have a default shipping category so assume so.
        }

        return app(Purchasables::class)->isPurchasableShippable($this->getPurchasable(), $this->getOrder());
    }

    public function getTax(): float
    {
        return $this->_getAdjustmentsTotalByType('tax');
    }

    public function getTaxIncluded(): float
    {
        return $this->_getAdjustmentsTotalByType('tax', true);
    }

    public function getShippingCost(): float
    {
        return $this->_getAdjustmentsTotalByType('shipping');
    }

    public function getDiscount(): float
    {
        return $this->_getAdjustmentsTotalByType('discount');
    }

    private function _getTeller(): Teller
    {
        if (!$order = $this->getOrder()) {
            throw new InvalidConfigException('Line Item requires an order to calculate costs.');
        }

        return $order->getTeller();
    }
}
