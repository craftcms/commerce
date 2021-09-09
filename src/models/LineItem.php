<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\Currency as CurrencyHelper;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\commerce\services\LineItemStatuses;
use craft\commerce\services\Orders;
use craft\errors\DeprecationException;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use DateTime;
use LitEmoji\LitEmoji;
use yii\base\InvalidConfigException;
use yii\behaviors\AttributeTypecastBehavior;

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
 * @method void typecastAttributes() Typecast behaviour
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItem extends Model
{
    /**
     * @var int|null ID
     */
    public $id;

    /**
     * @var string Description
     */
    private $_description;

    /**
     * @var float Price is the original price of the purchasable
     */
    private $_price = 0;

    /**
     * @var float Sale price is the price of the line item. Sale price is price + saleAmount
     */
    private $_salePrice = 0;

    /**
     * @var float Weight
     */
    public $weight = 0;

    /**
     * @var float Length
     */
    public $length = 0;

    /**
     * @var float Height
     */
    public $height = 0;

    /**
     * @var float Width
     */
    public $width = 0;

    /**
     * @var int Quantity
     */
    public $qty;

    /**
     * @var mixed Snapshot
     */
    public $snapshot;

    /**
     * @var string SKU
     */
    private $_sku;

    /**
     * @var string Note
     */
    public $note;

    /**
     * @var string Private Note
     */
    public $privateNote;

    /**
     * @var int Purchasable ID
     */
    public $purchasableId;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Line Item Status ID
     */
    public $lineItemStatusId;

    /**
     * @var int Tax category ID
     */
    public $taxCategoryId;

    /**
     * @var int Shipping category ID
     */
    public $shippingCategoryId;

    /**
     * @var DateTime|null
     * @since 2.2
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.2.0
     */
    public $dateUpdated;

    /**
     * @var string UID
     */
    public $uid;

    /**
     * @var PurchasableInterface Purchasable
     */
    private $_purchasable;

    /**
     * @var Order Order|null
     */
    private $_order;

    /**
     * @var LineItemStatus Line item status
     */
    private $_lineItemStatus;

    /**
     * @var
     */
    private $_options = [];

    /**
     * @inheritDoc
     */
    public function init()
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

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::class,
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'taxCategoryId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'shippingCategoryId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'lineItemStatusId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'orderId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'note' => AttributeTypecastBehavior::TYPE_STRING,
                'privateNote' => AttributeTypecastBehavior::TYPE_STRING,
                'width' => AttributeTypecastBehavior::TYPE_FLOAT,
                'height' => AttributeTypecastBehavior::TYPE_FLOAT,
                'length' => AttributeTypecastBehavior::TYPE_FLOAT,
                'weight' => AttributeTypecastBehavior::TYPE_FLOAT,
                'qty' => AttributeTypecastBehavior::TYPE_INTEGER,
                'price' => AttributeTypecastBehavior::TYPE_FLOAT,
                'salePrice' => AttributeTypecastBehavior::TYPE_FLOAT,
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
     * @return Order|null
     */
    public function getOrder()
    {
        if (null === $this->_order && null !== $this->orderId) {
            /** @var Orders $orderService */
            $orderService = Plugin::getInstance()->getOrders();
            $this->_order = $orderService->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order)
    {
        $this->orderId = $order->id;
        $this->_order = $order;
    }

    /**
     * @return LineItemStatus|null
     */
    public function getLineItemStatus()
    {
        if (null === $this->_lineItemStatus && null !== $this->lineItemStatusId) {
            /** @var LineItemStatuses $lineItemStatus */
            $lineItemStatus = Plugin::getInstance()->getLineItemStatuses();
            $this->_lineItemStatus = $lineItemStatus->getLineItemStatusById($this->lineItemStatusId);
        }

        return $this->_lineItemStatus;
    }

    /**
     * @param LineItemStatus|null $status
     * @since 3.2.2
     */
    public function setLineItemStatus(LineItemStatus $status = null)
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
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->_options;
    }

    /**
     * Set the options array on the line item.
     *
     * @param array|string $options
     */
    public function setOptions($options)
    {
        $options = Json::decodeIfJson($options);

        if (!is_array($options)) {
            $options = [];
        }

        $cleanEmojiValues = static function(&$options) use (&$cleanEmojiValues) {
            foreach ($options as $key => $value) {
                if (is_array($value)) {
                    $cleanEmojiValues($options[$key]);
                } else {
                    if (is_string($value)) {
                        $options[$key] = LitEmoji::unicodeToShortcode($value);
                    }
                }
            }

            return $options;
        };

        // TODO make this consistent no matter what the DB driver is. Will be a "breaking" change.
        if (Craft::$app->getDb()->getSupportsMb4()) {
            $this->_options = $options;
        } else {
            $this->_options = $cleanEmojiValues($options);
        }
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        if (!$this->_description) {
            $snapshot = Json::decodeIfJson($this->snapshot, true);
            $this->_description = $snapshot['description'] ?? '';
        }

        return $this->_description;
    }

    /**
     * @param $description
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        if (!$this->_sku) {
            $snapshot = Json::decodeIfJson($this->snapshot, true);
            $this->_sku = $snapshot['sku'] ?? '';
        }

        return $this->_sku;
    }

    /**
     * @param $sku
     */
    public function setSku($sku)
    {
        $this->_sku = $sku;
    }

    /**
     * Returns a unique hash of the line item options
     */
    public function getOptionsSignature()
    {
        return LineItemHelper::generateOptionsSignature($this->_options);
    }

    /**
     * @return float
     * @since 3.1.1
     */
    public function getPrice()
    {
        return $this->_price;
    }

    /**
     * @param $price
     * @since 3.1.1
     */
    public function setPrice($price)
    {
        $this->_price = $price;
    }

    /**
     * @return float Sale Price
     */
    public function getSalePrice()
    {
        return $this->_salePrice;
    }

    /**
     * @param $salePrice
     * @since 3.1.1
     */
    public function setSalePrice($salePrice)
    {
        $this->_salePrice = $salePrice;
    }

    /**
     * @param $saleAmount
     * @throws DeprecationException
     * @since 3.1.1
     * @deprecated in 3.1.1
     */
    public function setSaleAmount($saleAmount)
    {
        Craft::$app->getDeprecator()->log('LineItem::setSaleAmount()', 'The setting of `saleAmount` has been deprecated. `saleAmount` is automatically calculated.');
    }

    /**
     * @return float
     * @since 3.1.1
     */
    public function getSaleAmount()
    {
        return $this->price - $this->salePrice;
    }

    /**
     * @return array
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
            /** @var PurchasableInterface $purchasable */
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
    private function _normalizePurchasableRule($rule, PurchasableInterface $purchasable)
    {
        if (isset($rule[1]) && $rule[1] instanceof \Closure) {
            $method = $rule[1];
            $method->bindTo($purchasable);
            $rule[1] = function($attribute, $params, $validator, $current) use ($method) {
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

        if ($this->getBehavior('currencyAttributes')) {
            array_merge($fields, $this->getBehavior('currencyAttributes')->currencyFields());
        }

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
     *
     * @return array
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

    /**
     * @return float
     */
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
     * @return float
     */
    public function getTotal(): float
    {
        return $this->getSubtotal() + $this->getAdjustmentsTotal();
    }

    /**
     * @param $taxable
     * @return float|int
     */
    public function getTaxableSubtotal($taxable)
    {
        switch ($taxable) {
            case TaxRateRecord::TAXABLE_PRICE:
                $taxableSubtotal = $this->getSubtotal() + $this->getDiscount();
                break;
            case TaxRateRecord::TAXABLE_SHIPPING:
                $taxableSubtotal = $this->getShippingCost();
                break;
            case TaxRateRecord::TAXABLE_PRICE_SHIPPING:
                $taxableSubtotal = $this->getSubtotal() + $this->getDiscount() + $this->getShippingCost();
                break;
            default:
                $taxableSubtotal = $this->getSubtotal() + $this->getDiscount();
        }

        return $taxableSubtotal;
    }

    /**
     * @return bool False when no related purchasable exists
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

    /**
     * @return PurchasableInterface|null
     */
    public function getPurchasable()
    {
        if (null === $this->_purchasable && null !== $this->purchasableId) {
            $this->_purchasable = Craft::$app->getElements()->getElementById($this->purchasableId);
        }

        return $this->_purchasable;
    }

    /**
     * @param PurchasableInterface $purchasable
     */
    public function setPurchasable(PurchasableInterface $purchasable)
    {
        $this->purchasableId = $purchasable->getId();
        $this->_purchasable = $purchasable;
    }

    /**
     * @param PurchasableInterface $purchasable
     *
     */
    public function populateFromPurchasable(PurchasableInterface $purchasable)
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

    /**
     * @return bool
     */
    public function getOnSale(): bool
    {
        return $this->getSaleAmount() > 0;
    }

    /**
     * @return TaxCategory
     * @throws InvalidConfigException
     */
    public function getTaxCategory(): TaxCategory
    {
        if (null === $this->taxCategoryId) {
            throw new InvalidConfigException('Line Item is missing its tax category ID');
        }

        return Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
    }

    /**
     * @return ShippingCategory
     * @throws InvalidConfigException
     */
    public function getShippingCategory(): ShippingCategory
    {
        if (null === $this->shippingCategoryId) {
            throw new InvalidConfigException('Line Item is missing its shipping category ID');
        }

        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($this->shippingCategoryId);
    }

    /**
     * @return OrderAdjustment[]
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
     * @param bool $included
     * @return float
     */
    public function getAdjustmentsTotal($included = false): float
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
     * @param string $type
     * @param bool $included
     * @return float|int
     * @deprecated in 2.2
     */
    public function getAdjustmentsTotalByType($type, $included = false)
    {
        Craft::$app->getDeprecator()->log('LineItem::getAdjustmentsTotalByType()', '`LineItem::getAdjustmentsTotalByType()` has been deprecated. Use `LineItem::getTax()`, `LineItem::getDiscount()`, or `LineItem::getShippingCost()` instead.');

        return $this->_getAdjustmentsTotalByType($type, $included);
    }

    /**
     * @param string $type
     * @param bool $included
     * @return float|int
     */
    private function _getAdjustmentsTotalByType($type, $included = false)
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
     * @return bool
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
     * @return bool
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
     * @return float
     */
    public function getTax(): float
    {
        return $this->_getAdjustmentsTotalByType('tax');
    }

    /**
     * @return float
     */
    public function getTaxIncluded(): float
    {
        return $this->_getAdjustmentsTotalByType('tax', true);
    }

    /**
     * @return float
     */
    public function getShippingCost(): float
    {
        return $this->_getAdjustmentsTotalByType('shipping');
    }

    /**
     * @return float
     */
    public function getDiscount(): float
    {
        return $this->_getAdjustmentsTotalByType('discount');
    }
}
