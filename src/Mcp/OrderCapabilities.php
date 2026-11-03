<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Mcp;

use CraftCms\Cms\Mcp\Attributes\RequiresPermission;
use CraftCms\Cms\Mcp\McpActor;
use CraftCms\Commerce\Order\OrderManagement;
use CraftCms\Commerce\Payment\PaymentManagement;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

readonly class OrderCapabilities
{
    use CallsCommerce;

    public function __construct(private McpActor $actor, private OrderManagement $orders, private PaymentManagement $payments)
    {
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.orders.related', description: 'Reads line items, transactions, status history, adjustments or inventory fulfillment for an order. Payment credentials and raw gateway responses are excluded.', annotations: new ToolAnnotations(readOnlyHint: true))]
    #[RequiresPermission('commerce-manageOrders')]
    public function related(int $orderId, #[Schema(enum: ['lineItems', 'transactions', 'history', 'adjustments', 'fulfillment'])] string $relation, int $limit = 100, int $offset = 0): array
    {
        return $this->call(fn() => $this->orders->related($this->actor->user(), $orderId, $relation, $limit, $offset));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.orders.set-line-item', description: 'Sets an existing line item quantity or adds a purchasable. Provide exactly one of lineItemId or purchasableId. Quantity zero removes the item. Recalculates Commerce totals.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-editOrders')]
    public function setLineItem(int $orderId, #[Schema(minimum: 0)] int $quantity, ?int $lineItemId = null, ?int $purchasableId = null, #[Schema(type: 'object')] array $options = [], ?string $note = null): array
    {
        return $this->call(fn() => ['order' => ElementData::attributes($this->orders->setLineItem($this->actor->user(), $orderId, $quantity, $lineItemId, $purchasableId, $options, $note))]);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.orders.set-status', description: 'Changes a completed order status. Commerce records history and sends configured status emails.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-editOrders')]
    public function status(int $orderId, int $statusId, ?string $message = null): array
    {
        return $this->call(fn() => ['order' => ElementData::attributes($this->orders->status($this->actor->user(), $orderId, $statusId, $message))]);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.orders.complete', description: 'Administratively completes a cart without charging a payment. Triggers completion events, inventory commitments and configured emails. Confirm this intent first.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-editOrders')]
    public function complete(int $orderId): array
    {
        return $this->call(fn() => ['order' => ElementData::attributes($this->orders->complete($this->actor->user(), $orderId))]);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.orders.recalculate', description: 'Recalculates cart prices and adjustments, or just adjustments for completed orders.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-editOrders')]
    public function recalculate(int $orderId): array
    {
        return $this->call(fn() => ['order' => ElementData::attributes($this->orders->recalculate($this->actor->user(), $orderId))]);
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.orders.send-email', description: 'Sends an enabled Commerce email for an order using its configured recipients and template. Repeating sends another email.')]
    #[RequiresPermission('commerce-editOrders')]
    public function sendEmail(int $orderId, int $emailId): array
    {
        return $this->call(fn() => $this->orders->sendEmail($this->actor->user(), $orderId, $emailId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.orders.fulfill', description: 'Fulfills committed inventory for a line item from one location. Cannot exceed that line item commitment.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-editOrders')]
    public function fulfill(int $orderId, int $lineItemId, int $locationId, #[Schema(minimum: 1)] int $quantity): array
    {
        return $this->call(fn() => $this->orders->fulfill($this->actor->user(), $orderId, $lineItemId, $locationId, $quantity));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.payments.capture', description: 'Captures an authorized transaction through its gateway. This moves real money. Inspect returned transaction.status; do not retry blindly.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-capturePayment')]
    public function capture(int $transactionId): array
    {
        return $this->call(fn() => $this->payments->capture($this->actor->user(), $transactionId));
    }

    /** @return array<string, mixed> */
    #[McpTool(name: 'commerce.payments.refund', description: 'Refunds a transaction through its gateway. amount is in paymentCurrency; omit it for the remaining refundable amount. This moves real money. Inspect transaction.status before any retry.', annotations: new ToolAnnotations(destructiveHint: true))]
    #[RequiresPermission('commerce-refundPayment')]
    public function refund(int $transactionId, ?float $amount = null, string $note = ''): array
    {
        return $this->call(fn() => $this->payments->refund($this->actor->user(), $transactionId, $amount, $note));
    }
}
