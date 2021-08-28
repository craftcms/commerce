<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\helpers\Json;
use yii\base\InvalidArgumentException;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Order adjustment model.
 *
 * @property Order|null $order
 * @property LineItem|null $lineItem
 * @property array $sourceSnapshot
 * @property-read string $currency
 * @property-read string $amountAsCurrency
 * @method void typecastAttributes()
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderAdjustment extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Description
     */
    public string $description;

    /**
     * @var string Type
     */
    public string $type;

    /**
     * @var float Amount
     */
    public float $amount;

    /**
     * @var bool Included
     */
    public bool $included = false;

    /**
     * @var mixed Adjuster options
     */
    private $_sourceSnapshot = [];

    /**
     * @var int|null Order ID
     */
    public ?int $orderId;

    /**
     * @var int|null Line item ID this adjustment belongs to
     */
    public ?int $lineItemId = null;

    /**
     * @var bool Whether the adjustment is based of estimated data
     */
    public bool $isEstimated = false;

    /**
     * @var LineItem|null The line item this adjustment belongs to
     */
    private ?LineItem $_lineItem = null;

    /**
     * @var Order|null The order this adjustment belongs to
     */
    private ?Order $_order;


    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::class,
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'lineItemId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'orderId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'included' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'isEstimated' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'type' => AttributeTypecastBehavior::TYPE_STRING,
                'amount' => AttributeTypecastBehavior::TYPE_FLOAT,
                'name' => AttributeTypecastBehavior::TYPE_STRING,
                'description' => AttributeTypecastBehavior::TYPE_STRING
            ]
        ];

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'defaultCurrency' => Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso(),
            'currencyAttributes' => $this->currencyAttributes()
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['type', 'amount', 'sourceSnapshot', 'orderId'], 'required'],
            [['amount'], 'number'],
            [['orderId'], 'integer'],
            [['lineItemId'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $attributes = parent::attributes();
        $attributes[] = 'sourceSnapshot';

        return $attributes;
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     *
     * @return array
     */
    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'amount';
        return $attributes;
    }

    /**
     * @return string
     */
    protected function getCurrency(): string
    {
        return $this->_order->currency;
    }

    /**
     * Gets the options for the line item.
     */
    public function getSourceSnapshot(): array
    {
        return $this->_sourceSnapshot;
    }

    /**
     * Set the options array on the line item.
     *
     * @param array|string $snapshot
     */
    public function setSourceSnapshot($snapshot): void
    {
        if (is_string($snapshot)) {
            $snapshot = Json::decode($snapshot);
        }

        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Adjustment source snapshot must be an array.');
        }

        $this->_sourceSnapshot = $snapshot;
    }

    /**
     * @return LineItem|null
     */
    public function getLineItem(): ?LineItem
    {
        if ($this->_lineItem === null && isset($this->lineItemId) && $this->lineItemId) {
            $this->_lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($this->lineItemId);
        }

        return $this->_lineItem;
    }

    /**
     * @param LineItem $lineItem
     * @return void
     */
    public function setLineItem(LineItem $lineItem): void
    {
        $this->_lineItem = $lineItem;
    }

    /**
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && isset($this->orderId) && $this->orderId) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }
}
