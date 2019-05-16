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
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\Currency as CurrencyHelper;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\commerce\services\Orders;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Line Item model representing a line item on an order.
 *
 * @property array|OrderAdjustment[] $adjustments
 * @property string $description the description from the snapshot of the purchasable
 * @property float $discount
 * @property bool $onSale
 * @property array $options
 * @property Order $order
 * @property Purchasable $purchasable
 * @property ShippingCategory $shippingCategory
 * @property int $shippingCost
 * @property string $sku the description from the snapshot of the purchasable
 * @property int $tax
 * @property float $total the subTotal plus any adjustments belonging to this line item
 * @property TaxCategory $taxCategory
 * @property int $taxIncluded
 * @property-read string $optionsSignature the unique hash of the options
 * @property-read float $subtotal the Purchasable’s sale price multiplied by the quantity of the line item
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItem extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int|null ID
     */
    public $id;

    /**
     * @var float Price is the original price of the purchasable
     */
    public $price = 0;

    /**
     * @var float Sale amount off the price, based on the sales applied to the purchasable.
     */
    public $saleAmount = 0;

    /**
     * @var float Sale price is the price of the line item. Sale price is price + saleAmount
     */
    public $salePrice = 0;

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
     * @var string Note
     */
    public $note;

    /**
     * @var int Purchasable ID
     */
    public $purchasableId;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Tax category ID
     */
    public $taxCategoryId;

    /**
     * @var int Shipping category ID
     */
    public $shippingCategoryId;

    /**
     * @var PurchasableInterface Purchasable
     */
    private $_purchasable;

    /**
     * @var Order Order
     */
    private $_order;

    /**
     * @var
     */
    private $_options = [];

    // Public Methods
    // =========================================================================

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        /** @var Orders $orderService */
        $orderService = Plugin::getInstance()->getOrders();

        if (null === $this->_order && null !== $this->orderId) {
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
        if (is_string($options)) {
            $options = Json::decode($options);
        }

        if (!is_array($options)) {
            throw new InvalidArgumentException('Options must be an array.');
        }

        $this->_options = $options;
    }

    /**
     * Returns a unique hash of the line item options
     */
    public function getOptionsSignature()
    {
        return LineItemHelper::generateOptionsSignature($this->_options);
    }


    /**
     * @return array
     */
    public function rules()
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
                    'total',
                    'qty',
                    'snapshot',
                    'taxCategoryId',
                    'shippingCategoryId'
                ], 'required'
            ],
            [['qty'], 'integer', 'min' => 1],
        ];

        if ($this->purchasableId) {
            /** @var PurchasableInterface $purchasable */
            $purchasable = Craft::$app->getElements()->getElementById($this->purchasableId);
            if ($purchasable && !empty($purchasableRules = $purchasable->getLineItemRules($this))) {
                array_push($rules, ...$purchasableRules);
            }
        }

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();
        ArrayHelper::removeValue($names, 'snapshot');

        $names[] = 'adjustments';
        $names[] = 'description';
        $names[] = 'options';
        $names[] = 'optionsSignature';
        $names[] = 'onSale';
        $names[] = 'sku';
        $names[] = 'total';

        return $names;
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        return [
            'order',
            'purchasable',
            'shippingCategory',
            'taxCategory',
        ];
    }

    /**
     * @return float
     */
    public function getSubtotal(): float
    {
        // The subtotal should always be rounded.
        return $this->qty * $this->salePrice;
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
                $taxableSubtotal = $this->getSubtotal() + $this->getAdjustmentsTotalByType('discount');
                break;
            case TaxRateRecord::TAXABLE_SHIPPING:
                $taxableSubtotal = $this->getAdjustmentsTotalByType('shipping');
                break;
            case TaxRateRecord::TAXABLE_PRICE_SHIPPING:
                $taxableSubtotal = $this->getSubtotal() + $this->getAdjustmentsTotalByType('discount') + $this->getAdjustmentsTotalByType('shipping');
                break;
            default:
                $taxableSubtotal = $this->getSubtotal() + $this->getAdjustmentsTotalByType('discount');
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
        if (!$purchasable || !$purchasable->getIsAvailable()) {
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
     * @deprecated in 2.0 Use populateFromPurchasable() instead.
     */
    public function fillFromPurchasable(PurchasableInterface $purchasable)
    {
        Craft::$app->getDeprecator()->log('LineItemModel::fillFromPurchasable()', 'LineItemModel::fillFromPurchasable() has been deprecated by renaming. Use LineItem::populateFromPurchasable($purchasable)');

        $this->populateFromPurchasable($purchasable);
    }

    /**
     * @param PurchasableInterface $purchasable
     *
     */
    public function populateFromPurchasable(PurchasableInterface $purchasable)
    {
        $this->price = $purchasable->getPrice();
        $this->taxCategoryId = $purchasable->getTaxCategoryId();
        $this->shippingCategoryId = $purchasable->getShippingCategoryId();
        $this->salePrice = Plugin::getInstance()->getSales()->getSalePriceForPurchasable($purchasable, $this->order);
        $this->saleAmount = $this->salePrice - $this->price;

        $snapshot = [
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getId(),
            'cpEditUrl' => '#',
            'options' => $this->getOptions(),
            'sales' => Plugin::getInstance()->getSales()->getSalesForPurchasable($purchasable, $this->order)
        ];

        // Add our purchasable data to the snapshot, save our sales.
        $purchasableSnapshot = $purchasable->getSnapshot();
        $this->snapshot = array_merge($purchasableSnapshot, $snapshot);

        $purchasable->populateLineItem($this);

        $lineItemsService = Plugin::getInstance()->getLineItems();

        if ($lineItemsService->hasEventHandlers($lineItemsService::EVENT_POPULATE_LINE_ITEM)) {
            $lineItemsService->trigger($lineItemsService::EVENT_POPULATE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $this,
                'isNew' => !$this->id
            ]));
        }

        // If a plugin used the above event and changed the price of the product or
        // its saleAmount we need to ensure the salePrice works calculates correctly and is rounded
        $this->salePrice = CurrencyHelper::round($this->saleAmount + $this->price);

        // salePrice can not be negative
        $this->salePrice = max($this->salePrice, 0);
    }

    /**
     * @return bool
     */
    public function getOnSale(): bool
    {
        if (null !== $this->salePrice) {
            return CurrencyHelper::round($this->salePrice) !== CurrencyHelper::round($this->price);
        }

        return false;
    }

    /**
     * Returns the description from the snapshot of the purchasable
     */
    public function getDescription(): string
    {
        $purchasable = $this->getPurchasable();
        $snapshotDescription = isset($this->snapshot['description']) ? Html::decode($this->snapshot['description']) : '';
        $liveDescription = $purchasable ? $purchasable->getDescription() : '';

        return $snapshotDescription ?: $liveDescription;
    }

    /**
     * Returns the Sku from the snapshot of the purchasable
     */
    public function getSku(): string
    {
        $purchasable = $this->getPurchasable();
        $snapshotSku = isset($this->snapshot['sku']) ? Html::decode($this->snapshot['sku']) : '';
        $liveSku = $purchasable ? $purchasable->getSku() : '';

        return $snapshotSku ?: $liveSku;
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
            $hasLineItemId = (bool)$adjustment->lineItemId;
            $hasLineItem = (bool)$adjustment->getLineItem();

            if (($hasLineItemId && $adjustment->lineItemId == $this->id) || ($hasLineItem && $adjustment->getLineItem() === $this)) {
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
     * @param      $type
     * @param bool $included
     * @return float|int
     */
    public function getAdjustmentsTotalByType($type, $included = false)
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
     * @return float
     * @deprecated since 2.0
     */
    public function getTax(): float
    {
        Craft::$app->getDeprecator()->log('LineItem::getTax()', 'craft\commerce\models\LineItem::getTax() has been deprecated. Use getAdjustmentsTotalByType(\'tax\') instead.');

        return $this->getAdjustmentsTotalByType('tax');
    }

    /**
     * @return float
     * @deprecated since 2.0
     */
    public function getTaxIncluded(): float
    {
        Craft::$app->getDeprecator()->log('LineItem::getTaxIncluded()', 'craft\commerce\models\LineItem::getTaxIncluded() has been deprecated. Use getAdjustmentsTotalByType(\'taxIncluded\', true) instead.');

        return $this->getAdjustmentsTotalByType('taxIncluded', true);
    }

    /**
     * @return float
     * @deprecated since 2.0
     */
    public function getShippingCost(): float
    {
        Craft::$app->getDeprecator()->log('LineItem::getShippingCost()', 'craft\commerce\models\LineItem::getShippingCost() has been deprecated. Use getAdjustmentsTotalByType(\'shipping\') instead.');

        return $this->getAdjustmentsTotalByType('shipping');
    }

    /**
     * @return float
     * @deprecated since 2.0
     */
    public function getDiscount(): float
    {
        Craft::$app->getDeprecator()->log('LineItem::getDiscount()', 'craft\commerce\models\LineItem::getDiscount() has been deprecated. Use getAdjustmentsTotalByType(\'discount\') instead.');

        return $this->getAdjustmentsTotalByType('discount');
    }
}
