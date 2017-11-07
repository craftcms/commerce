<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Element;
use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\LineItemEvent;
use craft\commerce\helpers\Currency as CurrencyHelper;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\commerce\services\Orders;
use craft\helpers\Html;
use yii\base\InvalidConfigException;

/**
 * Line Item model representing a line item on an order.
 *
 * @property int                     $id
 * @property float                   $price
 * @property float                   $saleAmount
 * @property float                   $salePrice
 * @property float                   $weight
 * @property float                   $height
 * @property float                   $width
 * @property float                   $length
 * @property float                   $total
 * @property int                     $qty
 * @property string                  $note
 * @property array                   $snapshot
 * @property int                     $orderId
 * @property int                     $purchasableId
 * @property string                  $optionsSignature
 * @property mixed                   $options
 * @property int                     $taxCategoryId
 * @property int                     $shippingCategoryId
 * @property bool                    $onSale
 * @property Purchasable             $purchasable
 * @property Order                   $order
 * @property TaxCategory             $taxCategory
 * @property ShippingCategory        $shippingCategory
 * @property int                     $shippingCost
 * @property string                  $sku
 * @property float                   $subtotal
 * @property int                     $tax
 * @property string                  $description
 * @property array|OrderAdjustment[] $adjustments
 * @property int                     $taxIncluded
 * @property float                   $discount
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class LineItem extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var mixed Options
     */
    public $options;

    /**
     * @var string Options Signature Hash
     */
    public $optionsSignature;

    /**
     * @var float Price
     */
    public $price = 0;

    /**
     * @var float Sale amount
     */
    public $saleAmount = 0;

    /**
     * @var float Sale price
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
     * @var float Total
     */
    public $total = 0;

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
     * @return array
     */
    public function rules(): array
    {
        return [
            [
                [
                    'optionsSignature',
                    'price',
                    'saleAmount',
                    'salePrice',
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
            ]
        ];
    }

    /**
     * @return float
     */
    public function getSubtotal(): float
    {
        // The subtotal should always be rounded.
        return $this->qty * CurrencyHelper::round($this->salePrice);
    }

    /**
     * Returns the Purchasable’s sale price multiplied by the quantity of the line item.
     *
     * @return float
     */
    public function getTotal(): float
    {
        return $this->getSubtotal() + $this->getAdjustmentsTotal();
    }

    /**
     * @param $taxable
     *
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
     * @return bool False when no related purchasable exists or order complete.
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

        $this->fillFromPurchasable($purchasable);

        return true;
    }

    /**
     * @return PurchasableInterface|null
     *
     * @throws InvalidConfigException
     */
    public function getPurchasable()
    {
        if (null === $this->_purchasable && null !== $this->purchasableId) {
            $this->_purchasable = Craft::$app->getElements()->getElementById($this->purchasableId);
        }

        if (null !== $this->_purchasable && !($this->_purchasable instanceof PurchasableInterface)) {
            throw new InvalidConfigException('Line item should only contain a real purchasable.');
        }

        return $this->_purchasable;
    }

    /**
     * @param \craft\commerce\base\Element $purchasable
     *
     * @return void
     */
    public function setPurchasable(Element $purchasable)
    {
        $this->_purchasable = $purchasable;
    }

    /**
     * @param PurchasableInterface $purchasable
     */
    public function fillFromPurchasable(PurchasableInterface $purchasable)
    {
        $this->price = $purchasable->getPrice();
        $this->taxCategoryId = $purchasable->getTaxCategoryId();
        $this->shippingCategoryId = $purchasable->getShippingCategoryId();

        // Since sales cannot apply to non core purchasables yet, set to price at default
        $this->salePrice = $purchasable->getPrice();
        $this->saleAmount = 0;

        $snapshot = [
            'price' => $purchasable->getPrice(),
            'sku' => $purchasable->getSku(),
            'description' => $purchasable->getDescription(),
            'purchasableId' => $purchasable->getPurchasableId(),
            'cpEditUrl' => '#',
            'options' => $this->options
        ];

        // Add our purchasable data to the snapshot, save our sales.
        $this->snapshot = array_merge($purchasable->getSnapshot(), $snapshot);

        $purchasable->populateLineItem($this);

        $lineItemsService = Plugin::getInstance()->getLineItems();

        // Raise the 'populateLineItem' event
        if ($lineItemsService->hasEventHandlers($lineItemsService::EVENT_POPULATE_LINE_ITEM)) {
            $lineItemsService->trigger($lineItemsService::EVENT_POPULATE_LINE_ITEM, new LineItemEvent([
                'lineItem' => $this,
                'purchasable' => $this->purchasable
            ]));
        }

        // Always make sure salePrice is equal to the price and saleAmount
        $this->salePrice = CurrencyHelper::round($this->saleAmount + $this->price);
    }

    /**
     * @return bool
     */
    public function getOnSale(): bool
    {
        if (null !== $this->salePrice)
        {
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
        $snapShotDescription = isset($this->snapshot['description']) ? Html::decode($this->snapshot['description']) : '';
        $liveDescription = $purchasable ? $purchasable->getDescription() : '';

        return $snapShotDescription ?: $liveDescription;
    }

    /**
     * Returns the description from the snapshot of the purchasable
     */
    public function getSku(): string
    {
        $purchasable = $this->getPurchasable();
        $snapShotSku = isset($this->snapshot['sku']) ? Html::decode($this->snapshot['sku']) : '';
        $liveSku = $purchasable ? $purchasable->getSku() : '';

        return $snapShotSku ?: $liveSku;
    }

    /**
     * @return TaxCategory
     *
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
     *
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
            if ($adjustment->lineItemId === $this->id) {
                $lineItemAdjustments[] = $adjustment;
            }
        }

        return $lineItemAdjustments;
    }

    /**
     * @param bool $included
     *
     * @return float
     */
    public function getAdjustmentsTotal($included = false): float
    {
        $amount = 0;
        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included === $included) {
                $amount += $adjustment->amount;
            }
        }

        return $amount;
    }

    /**
     * @param      $type
     * @param bool $included
     *
     * @return float|int
     */
    public function getAdjustmentsTotalByType($type, $included = false)
    {
        $amount = 0;

        foreach ($this->getAdjustments() as $adjustment) {
            if ($adjustment->included === $included && $adjustment->type === $type) {
                $amount += $adjustment->amount;
            }
        }

        return $amount;
    }

    /**
     * @return float
     */
    public function getTax(): float
    {
        Craft::$app->getDeprecator()->log('VariantModel::getTax()', 'VariantModel::getTax() has been deprecated. Use LineItem::getAdjustmentsTotalByType($type) ');

        return $this->getAdjustmentsTotalByType('tax');
    }

    /**
     * @return float
     */
    public function getTaxIncluded(): float
    {
        Craft::$app->getDeprecator()->log('VariantModel::getTaxIncluded()', 'VariantModel::getTaxIncluded() has been deprecated. Use LineItem::getAdjustmentsTotalByType($type)');

        return $this->getAdjustmentsTotalByType('taxIncluded', true);
    }

    /**
     * @deprecated since 2.0
     *
     * @return float
     */
    public function getShippingCost(): float
    {
        Craft::$app->getDeprecator()->log('VariantModel::getShippingCost()', 'VariantModel::getShippingCost() has been deprecated. Use LineItem::getAdjustmentsTotalByType($type)');

        return $this->getAdjustmentsTotalByType('shipping');
    }

    /**
     * @deprecated since 2.0
     *
     * @return float
     */
    public function getDiscount(): float
    {
        Craft::$app->getDeprecator()->log('VariantModel::getDiscount()', 'VariantModel::getDiscount() has been deprecated. Use LineItem::getAdjustmentsTotalByType($type)');

        return $this->getAdjustmentsTotalByType('discount');
    }
}
