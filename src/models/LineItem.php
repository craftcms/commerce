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
 * @property bool $onSale
 * @property array $options
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
 * @property-read float $saleAmount
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
     * @var float Sale price is the price of the line item. Sale price is price + saleAmount
     */
    private float $_salePrice = 0;

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
     * @var mixed Snapshot
     * @TODO add get and set methods in 5.0.0. Strict typing will be enforced.
     */
    public mixed $snapshot = null;

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
            $this->_lineItemStatus = $lineItemStatus->getLineItemStatusById($this->lineItemStatusId);
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
     * @return string
     */
    public function getDescription(): string
    {
        if (!$this->_description) {
            $snapshot = Json::decodeIfJson($this->snapshot, true);
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
            $snapshot = Json::decodeIfJson($this->snapshot, true);
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
        return LineItemHelper::generateOptionsSignature($this->_options);
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
    }

    /**
     * @return float Sale Price
     */
    public function getSalePrice(): float
    {
        return CurrencyHelper::round($this->_salePrice);
    }

    /**
     * @since 3.1.1
     */
    public function setSalePrice(float|int $salePrice): void
    {
        $this->_salePrice = $salePrice;
    }

    /**
     * @since 3.1.1
     */
    public function getSaleAmount(): float
    {
        return Currency::round($this->price - $this->salePrice);
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
                    'salePrice',
                    'saleAmount',
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
            [['price', 'salePrice'], 'number'],
        ];

        if ($this->purchasableId) {
            /** @var PurchasableInterface|null $purchasable */
            $purchasable = Craft::$app->getElements()->getElementById($this->purchasableId);
            if ($purchasable && !empty($purchasableRules = $purchasable->getLineItemRules($this))) {
                foreach ($purchasableRules as $rule) {
                    $rules[] = $this->_normalizePurchasableRule($rule, $purchasable);
                }
            }
        }

        return $rules;
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
        $names[] = 'onSale';
        $names[] = 'price';
        $names[] = 'saleAmount';
        $names[] = 'salePrice';
        $names[] = 'sku';
        $names[] = 'total';

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
        $attributes[] = 'saleAmount';
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
            /** @var PurchasableInterface|null $purchasable */
            $purchasable = Craft::$app->getElements()->getElementById($this->purchasableId);
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
        $this->price = $purchasable->getPrice();
        $this->salePrice = Plugin::getInstance()->getSales()->getSalePriceForPurchasable($purchasable, $this->order);
        $this->taxCategoryId = $purchasable->getTaxCategoryId();
        $this->shippingCategoryId = $purchasable->getShippingCategoryId();
        $this->setSku($purchasable->getSku());
        $this->setDescription($purchasable->getDescription());

        // Check to see if there is a discount applied that ignores Sales for this line item
        $ignoreSales = false;
        foreach (Plugin::getInstance()->getDiscounts()->getAllActiveDiscounts($this->getOrder()) as $discount) {
            if (Plugin::getInstance()->getDiscounts()->matchLineItem($this, $discount, true)) {
                $ignoreSales = $discount->ignoreSales;
                if ($ignoreSales) {
                    break;
                }
            }
        }

        // One of the matching discounts has ignored sales, so we don't want the salePrice to be the original price.
        if ($ignoreSales) {
            $this->salePrice = $this->price;
        }

        $snapshot = [
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getId(),
            'cpEditUrl' => '#',
            'options' => $this->getOptions(),
            'sales' => $ignoreSales ? [] : Plugin::getInstance()->getSales()->getSalesForPurchasable($purchasable, $this->order),
        ];

        // Add our purchasable data to the snapshot, save our sales.
        $purchasableSnapshot = $purchasable->getSnapshot();
        $this->snapshot = array_merge($purchasableSnapshot, $snapshot);

        $purchasable->populateLineItem($this);

        $lineItemsService = Plugin::getInstance()->getLineItems();

        if ($lineItemsService->hasEventHandlers($lineItemsService::EVENT_POPULATE_LINE_ITEM)) {
            $lineItemsService->trigger($lineItemsService::EVENT_POPULATE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $this,
                'isNew' => !$this->id,
            ]));
        }
    }

    public function getOnSale(): bool
    {
        return $this->getSaleAmount() > 0;
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
        $categories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories(true);
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
