<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Closure;
use Craft;
use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\Currency as CurrencyHelper;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\errors\DeprecationException;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use DateTime;
use LitEmoji\LitEmoji;
use yii\base\InvalidConfigException;

/**
 * Line Item model representing a line item on an order.
 *
 * @property array|OrderAdjustment[] $adjustments
 * @property float $discount
 * @property bool $onPromotion
 * @property array $options
 * @property array $snapshot
 * @property Order $order
 * @property Purchasable $purchasable
 * @property ShippingCategory $shippingCategory
 * @property int $shippingCost
 * @property int $tax
 * @property float $total the subTotal plus any adjustments belonging to this line item
 * @property TaxCategory $taxCategory
 * @property int $taxIncluded
 * @property-read string $optionsSignature the unique hash of the options
 * @property-read float $subtotal the Purchasable’s sale price multiplied by the quantity of the line item
 * @property float $salePrice
 * @property float $price
 * @property-read string $priceAsCurrency
 * @property-read string $saleAmountAsCurrency
 * @property-read string $salePriceAsCurrency
 * @property-read string $subtotalAsCurrency
 * @property-read string $totalAsCurrency
 * @property-read string $discountAsCurrency
 * @property-read string $shippingCostAsCurrency
 * @property-read string $taxAsCurrency
 * @property-read string $taxIncludedAsCurrency
 * @property-read bool $isShippable
 * @property string $sku
 * @property LineItemStatus|null $lineItemStatus
 * @property string $description
 * @property-read bool $isTaxable
 * @property float|int $promotionalPrice
 * @property-read float $promotionalAmount
 * @property-read string $adjustmentsTotalAsCurrency
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItem extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string Description
     */
    private string $_description;

    /**
     * @var float Price is the original price of the purchasable
     */
    private float $_price = 0;

    /**
     * @var float|null
     * @since 5.0.0
     */
    private ?float $_promotionalPrice = null;

    /**
     * @var float|null Sale price is the price the line item will be sold for.
     */
    private ?float $_salePrice = null;

    /**
     * @var float Weight
     */
    public float $weight = 0;

    /**
     * @var float Length
     */
    public float $length = 0;

    /**
     * @var float Height
     */
    public float $height = 0;

    /**
     * @var float Width
     */
    public float $width = 0;

    /**
     * @var int Quantity
     */
    public int $qty;

    /**
     * @var array|null Snapshot
     */
    private ?array $_snapshot = null;

    /**
     * @var string SKU
     */
    private string $_sku;

    /**
     * @var string Note
     */
    public string $note = '';

    /**
     * @var string Private Note
     */
    public string $privateNote = '';

    /**
     * @var int|null Purchasable ID
     */
    public ?int $purchasableId = null;

    /**
     * @var int|null Order ID
     */
    public ?int $orderId = null;

    /**
     * @var int|null Line Item Status ID
     */
    public ?int $lineItemStatusId = null;

    /**
     * @var int Tax category ID
     */
    public int $taxCategoryId;

    /**
     * @var int Shipping category ID
     */
    public int $shippingCategoryId;

    /**
     * @var DateTime|null
     * @since 2.2
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.2.0
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var string UID
     */
    public string $uid;

    /**
     * @var PurchasableInterface|null Purchasable
     */
    private ?PurchasableInterface $_purchasable = null;

    /**
     * @var Order|null
     */
    private ?Order $_order = null;

    /**
     * @var LineItemStatus|null Line item status
     */
    private ?LineItemStatus $_lineItemStatus = null;

    /**
     * @var array
     */
    private array $_options = [];

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->note = LitEmoji::shortcodeToUnicode($this->note);
        $this->privateNote = LitEmoji::shortcodeToUnicode($this->privateNote);

        parent::init();
    }

    /**
     * @inheritDoc
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
     * @throws InvalidConfigException
     */
    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && isset($this->orderId) && $this->orderId) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    public function setOrder(Order $order): void
    {
        $this->orderId = $order->id;
        $this->_order = $order;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getLineItemStatus(): ?LineItemStatus
    {
        if (!isset($this->_lineItemStatus) && isset($this->lineItemStatusId)) {
            $lineItemStatus = Plugin::getInstance()->getLineItemStatuses();
            $this->_lineItemStatus = $lineItemStatus->getLineItemStatusById($this->lineItemStatusId, $this->getOrder()?->getStore()->id);
        }

        return $this->_lineItemStatus;
    }

    /**
     * @param LineItemStatus|null $status
     * @since 3.2.2
     */
    public function setLineItemStatus(LineItemStatus $status = null): void
    {
        if ($status !== null) {
            $this->_lineItemStatus = $status;
            $this->lineItemStatusId = (int)$status->id;
        } else {
            $this->lineItemStatusId = null;
            $this->_lineItemStatus = null;
        }
    }

    /**
     * Returns the options for the line item.
     */
    public function getOptions(): array
    {
        return $this->_options;
    }

    /**
     * Set the options array on the line item.
     */
    public function setOptions(array|string $options): void
    {
        $options = Json::decodeIfJson($options);

        if (!is_array($options)) {
            $options = [];
        }

        $cleanEmojiValues = static function(&$options) use (&$cleanEmojiValues) {
            foreach ($options as $key => $value) {
                if (is_array($value)) {
                    $cleanEmojiValues($value);
                } else {
                    if (is_string($value)) {
                        $options[$key] = LitEmoji::unicodeToShortcode($value);
                    }
                }
            }

            return $options;
        };

        // TODO make this consistent no matter what the DB driver is. Will be a "breaking" change. #COM-46
        if (Craft::$app->getDb()->getSupportsMb4()) {
            $this->_options = $options;
        } else {
            $this->_options = $cleanEmojiValues($options);
        }
    }

    /**
     * Returns the snapshot for the line item.
     *
     * @return array
     * @since 5.0.0
     */
    public function getSnapshot(): array
    {
        return $this->_snapshot ?? [];
    }

    /**
     * Set the snapshot array on the line item.
     *
     * @param array|string $snapshot
     * @return void
     * @since 5.0.0
     */
    public function setSnapshot(array|string $snapshot): void
    {
        $snapshot = Json::decodeIfJson($snapshot);

        if (!is_array($snapshot)) {
            $snapshot = [];
        }

        $this->_snapshot = $snapshot;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        if (!$this->_description) {
            $snapshot = $this->getSnapshot();
            $this->_description = $snapshot['description'] ?? '';
        }

        return $this->_description;
    }

    /**
     * @param ?string $description
     * @return void
     */
    public function setDescription(?string $description): void
    {
        $this->_description = (string)$description;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        if (!$this->_sku) {
            $snapshot = $this->getSnapshot();
            $this->_sku = $snapshot['sku'] ?? '';
        }

        return $this->_sku;
    }

    /**
     * @param ?string $sku
     * @return void
     */
    public function setSku(?string $sku): void
    {
        $this->_sku = (string)$sku;
    }

    /**
     * Returns a unique hash of the line item options
     */
    public function getOptionsSignature(): string
    {
        $orderId = $this->getOrder()?->isCompleted ? $this->id : null;

        return LineItemHelper::generateOptionsSignature($this->_options, $orderId);
    }

    /**
     * @since 3.1.1
     */
    public function getPrice(): float
    {
        return CurrencyHelper::round($this->_price);
    }

    /**
     * @since 3.1.1
     */
    public function setPrice(float|int $price): void
    {
        $this->_price = $price;
        // clear sale price cache
        $this->_salePrice = null;
    }

    /**
     * @return float|null
     * @since 5.0.0
     */
    public function getPromotionalPrice(): ?float
    {
        if ($this->_promotionalPrice === null) {
            return null;
        }

        return CurrencyHelper::round($this->_promotionalPrice);
    }

    /**
     * @param float|int|null $price
     * @return void
     * @since 5.0.0
     */
    public function setPromotionalPrice(float|int|null $price): void
    {
        $this->_promotionalPrice = $price;
        // clear sale price cache
        $this->_salePrice = null;
    }

    /**
     * @return float Sale Price
     */
    public function getSalePrice(): float
    {
        if ($this->_salePrice === null) {
            $this->_salePrice = $this->getOnPromotion() ? $this->getPromotionalPrice() : $this->getPrice();
        }

        return $this->_salePrice;
    }

    /**
     * @return float
     * @deprecated in 5.0.0. Use `getPromotionalAmount()` instead.)
     */
    public function getSaleAmount(): float
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'LineItem `getSaleAmount()` method has been deprecated. Use `getPromotionalAmount()` instead.');
        return $this->getPromotionalAmount();
    }

    /**
     * @return float
     * @since 5.0.0
     */
    public function getPromotionalAmount(): float
    {
        if ($this->getPromotionalPrice() === null) {
            return 0;
        }

        return Currency::round($this->getPrice() - $this->getPromotionalPrice());
    }

    /**
     * @inerhitdoc
     */
    protected function defineRules(): array
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
                    'snapshot',
                    'taxCategoryId',
                    'shippingCategoryId',
                ], 'required',
            ],
            [['qty'], 'integer', 'min' => 1],
            [['shippingCategoryId', 'taxCategoryId'], 'integer'],
            [['price'], 'number'],
            [['promotionalPrice'], 'number', 'skipOnEmpty' => true],
        ];

        if ($this->purchasableId) {
            $order = $this->getOrder();
            /** @var PurchasableInterface|null $purchasable */
            $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($this->purchasableId, $order?->orderSiteId, $order?->getCustomer()?->id);
            if ($purchasable && !empty($purchasableRules = $purchasable->getLineItemRules($this))) {
                foreach ($purchasableRules as $rule) {
                    $rules[] = $this->_normalizePurchasableRule($rule, $purchasable);
                }
            }
        }

        // TODO: If order is complete, qty can not be less that total fulfilled across locations

        return $rules;
    }

    /**
     * @return int
     */
    public function getFulfilledTotalQuantity(): int
    {
        if ($order = $this->getOrder()) {
            return Plugin::getInstance()->getInventory()->getInventoryFulfillmentLevels($order)
                ->filter(fn($fulfillment) => $fulfillment->getLineItem()->id === $this->id)
                ->sum('fulfilledQuantity');
        }

        return 0;
    }

    /**
     * Normalizes a purchasable’s validation rule.
     *
     * @param mixed $rule
     * @param PurchasableInterface $purchasable
     * @return mixed
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
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        ArrayHelper::removeValue($names, 'snapshot');

        $names[] = 'adjustments';
        $names[] = 'description';
        $names[] = 'options';
        $names[] = 'optionsSignature';
        $names[] = 'onPromotion';
        $names[] = 'price';
        $names[] = 'promotionalPrice';
        $names[] = 'salePrice';
        $names[] = 'sku';
        $names[] = 'total';
        $names[] = 'fulfilledTotalQuantity';

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function fields(): array
    {
        $fields = parent::fields(); // get the currency and date fields formatted
        $fields['subtotal'] = 'subtotal';

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        return [
            'lineItemStatus',
            'order',
            'purchasable',
            'shippingCategory',
            'snapshot',
            'taxCategory',
        ];
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     */
    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'price';
        $attributes[] = 'promotionalPrice';
        $attributes[] = 'promotionalAmount';
        $attributes[] = 'salePrice';
        $attributes[] = 'subtotal';
        $attributes[] = 'total';
        $attributes[] = 'discount';
        $attributes[] = 'shippingCost';
        $attributes[] = 'tax';
        $attributes[] = 'taxIncluded';
        $attributes[] = 'adjustmentsTotal';

        return $attributes;
    }

    public function getSubtotal(): float
    {
        // Even though we validate salePrice as numeric, we still need to
        // stop any exceptions from occurring when displaying subtotal on an order/lineitems with errors.
        if (!is_numeric($this->salePrice)) {
            $salePrice = 0;
        } else {
            $salePrice = $this->salePrice;
        }

        return CurrencyHelper::round($this->qty * $salePrice);
    }

    /**
     * Returns the Purchasable’s sale price multiplied by the quantity of the line item, plus any adjustment belonging to this lineitem.
     *
     * @throws InvalidConfigException
     */
    public function getTotal(): float
    {
        return $this->getSubtotal() + $this->getAdjustmentsTotal();
    }

    public function getTaxableSubtotal(string $taxable): float
    {
        return match ($taxable) {
            TaxRateRecord::TAXABLE_PRICE => $this->getSubtotal() + $this->getDiscount(),
            TaxRateRecord::TAXABLE_SHIPPING => $this->getShippingCost(),
            TaxRateRecord::TAXABLE_PRICE_SHIPPING => $this->getSubtotal() + $this->getDiscount() + $this->getShippingCost(),
            default => $this->getSubtotal() + $this->getDiscount(),
        };
    }

    /**
     * @return bool False when no related purchasable exists
     * @throws InvalidConfigException
     */
    public function refreshFromPurchasable(): bool
    {
        if ($this->qty <= 0 && $this->id) {
            return false;
        }

        /* @var $purchasable Purchasable */
        $purchasable = $this->getPurchasable();
        if (!$purchasable || !Plugin::getInstance()->getPurchasables()->isPurchasableAvailable($purchasable, $this->getOrder())) {
            return false;
        }

        $this->populateFromPurchasable($purchasable);

        return true;
    }

    public function getPurchasable(): ?PurchasableInterface
    {
        if (!isset($this->_purchasable) && isset($this->purchasableId)) {
            $order = $this->getOrder();
            /** @var PurchasableInterface|null $purchasable */
            $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($this->purchasableId, $order?->orderSiteId, $order?->getCustomer()?->id);
            $this->_purchasable = $purchasable;
        }

        return $this->_purchasable;
    }

    public function setPurchasable(PurchasableInterface $purchasable): void
    {
        $this->purchasableId = $purchasable->getId();
        $this->_purchasable = $purchasable;
    }

    /**
     * @throws InvalidConfigException
     */
    public function populateFromPurchasable(PurchasableInterface $purchasable): void
    {
        $this->setPrice($purchasable->getPrice());
        $this->setPromotionalPrice($purchasable->getPromotionalPrice());
        $this->taxCategoryId = $purchasable->getTaxCategory()->id;
        $this->shippingCategoryId = $purchasable->getShippingCategory()->id;
        $this->setSku($purchasable->getSku());
        $this->setDescription($purchasable->getDescription());

        // Check to see if there is a discount applied that ignores Sales for this line item
        $ignorePromotions = false;
        foreach (Plugin::getInstance()->getDiscounts()->getAllActiveDiscounts($this->getOrder()) as $discount) {
            if (Plugin::getInstance()->getDiscounts()->matchLineItem($this, $discount, true)) {
                $ignorePromotions = $discount->ignorePromotions;
                if ($ignorePromotions) {
                    break;
                }
            }
        }

        // One of the matching discounts has ignored promotions, so we want to remove any promotional price.
        if ($ignorePromotions) {
            $this->setPromotionalPrice(null);
        }

        $snapshot = [
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getId(),
            'cpEditUrl' => '#',
            'options' => $this->getOptions(),
            // Only add sales information to the snapshot if we are not ignoring promotions and they are still using the sales system.
            'sales' => $ignorePromotions || Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules() ? [] : Plugin::getInstance()->getSales()->getSalesForPurchasable($purchasable, $this->order),
        ];

        // Add our purchasable data to the snapshot, save our sales.
        $purchasableSnapshot = $purchasable->getSnapshot();
        $this->setSnapshot(array_merge($purchasableSnapshot, $snapshot));

        $purchasable->populateLineItem($this);

        $lineItemsService = Plugin::getInstance()->getLineItems();

        if ($lineItemsService->hasEventHandlers($lineItemsService::EVENT_POPULATE_LINE_ITEM)) {
            $lineItemsService->trigger($lineItemsService::EVENT_POPULATE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $this,
                'isNew' => !$this->id,
            ]));
        }
    }

    /**
     * @return bool
     * @since 5.0.0
     */
    public function getOnPromotion(): bool
    {
        return $this->getPromotionalAmount() > 0;
    }

    /**
     * @return bool
     * @throws DeprecationException
     */
    public function getOnSale(): bool
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'LineItem `' . __METHOD__ . '()` method has been deprecated. Use `getOnPromotion()` instead.');
        return $this->getOnPromotion();
    }

    /**
     * @throws InvalidConfigException
     */
    public function getTaxCategory(): TaxCategory
    {
        // Category may have been archived
        $categories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories(true);
        return ArrayHelper::firstWhere($categories, 'id', $this->taxCategoryId);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getShippingCategory(): ShippingCategory
    {
        if (!isset($this->shippingCategoryId)) {
            throw new InvalidConfigException('Line Item is missing its shipping category ID');
        }

        // Category may have been archived
        $categories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories(withTrashed: true);
        return ArrayHelper::firstWhere($categories, 'id', $this->shippingCategoryId);
    }

    /**
     * @return OrderAdjustment[]
     * @throws InvalidConfigException
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

    /**
     * @throws InvalidConfigException
     */
    public function getAdjustmentsTotal(bool $included = false): float
    {
        $amount = 0;
        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included == $included) {
                $amount += $adjustment->amount;
            }
        }

        return $amount;
    }

    /**
     * @throws InvalidConfigException
     */
    private function _getAdjustmentsTotalByType(string $type, bool $included = false): float|int
    {
        $amount = 0;

        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included == $included && $adjustment->type === $type) {
                $amount += $adjustment->amount;
            }
        }

        return $amount;
    }

    /**
     * @since 3.3.4
     */
    public function getIsTaxable(): bool
    {
        if (!$this->getPurchasable()) {
            return true; // we have a default tax category so assume so.
        }

        return $this->getPurchasable()->getIsTaxable();
    }

    /**
     * @since 3.4
     */
    public function getIsShippable(): bool
    {
        if (!$this->getPurchasable()) {
            return true; // we have a default shipping category so assume so.
        }

        return $this->getPurchasable()->getIsShippable();
    }

    /**
     * @throws InvalidConfigException
     */
    public function getTax(): float
    {
        return $this->_getAdjustmentsTotalByType('tax');
    }

    /**
     * @throws InvalidConfigException
     */
    public function getTaxIncluded(): float
    {
        return $this->_getAdjustmentsTotalByType('tax', true);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getShippingCost(): float
    {
        return $this->_getAdjustmentsTotalByType('shipping');
    }

    /**
     * @throws InvalidConfigException
     */
    public function getDiscount(): float
    {
        return $this->_getAdjustmentsTotalByType('discount');
    }
}
