<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\Orders;

/**
 * @property array|string $sourceSnapshot
 */
class OrderAdjustment extends Component
{
    public ?int $id = null;

    public string $name;

    public ?string $description = null;

    public string $type;

    public float $amount;

    public bool $included = false;

    public ?int $orderId = null;

    public ?int $lineItemId = null;

    public bool $isEstimated = false;

    private mixed $_sourceSnapshot = [];

    private ?LineItem $_lineItem = null;

    private ?Order $_order = null;

    public function getSourceSnapshot(): array
    {
        return $this->_sourceSnapshot;
    }

    public function setSourceSnapshot(array|string $snapshot): void
    {
        if (is_string($snapshot)) {
            $snapshot = Json::decode($snapshot);
        }

        if (!is_array($snapshot)) {
            throw new \InvalidArgumentException('Adjustment source snapshot must be an array.');
        }

        $this->_sourceSnapshot = $snapshot;
    }

    public function getLineItem(): ?LineItem
    {
        if ($this->_lineItem === null && $this->lineItemId) {
            $this->_lineItem = app(LineItems::class)->getLineItemById($this->lineItemId);
        }

        return $this->_lineItem;
    }

    public function setLineItem(LineItem $lineItem): void
    {
        $this->_lineItem = $lineItem;
    }

    public function getOrder(): ?Order
    {
        if (!isset($this->_order) && $this->orderId) {
            $this->_order = app(Orders::class)->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    public function setOrder(Order $order): void
    {
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    public function getAmountAsCurrency(): string
    {
        return Currency::formatAsCurrency($this->amount, $this->getOrder()?->currency);
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'type' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'sourceSnapshot' => ['required'],
            'orderId' => ['required', 'integer'],
            'lineItemId' => ['nullable', 'integer'],
        ];
    }

    #[\Override]
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'sourceSnapshot' => $this->_sourceSnapshot,
        ]);
    }
}
