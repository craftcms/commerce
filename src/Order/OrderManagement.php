<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use CraftCms\Cms\Element\UserInitiatedElementSave;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Data\InventoryFulfillMovement;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Purchasable\Purchasables;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

readonly class OrderManagement
{
    public function find(CraftUser $actor, int $id, string $ability = 'view'): Order
    {
        $order = Order::find()->id($id)->status(null)->one();
        if (!$order || !Gate::forUser($actor)->allows($ability, $order)) {
            throw new InvalidArgumentException('Resource not found.');
        }
        return $order;
    }

    public function related(CraftUser $actor, int $orderId, string $relation, int $limit = 100, int $offset = 0): array
    {
        $order = $this->find($actor, $orderId);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Use limit 1-100 and a nonnegative offset.');
        }
        $items = match ($relation) {
            'lineItems' => array_map(static fn($item) => ['id' => $item->id, 'purchasableId' => $item->purchasableId, 'sku' => $item->getSku(), 'description' => $item->getDescription(), 'qty' => $item->qty, 'price' => $item->price, 'salePrice' => $item->salePrice, 'total' => $item->getTotal(), 'note' => $item->note, 'options' => $item->getOptions(), 'lineItemStatusId' => $item->lineItemStatusId], $order->getLineItems()),
            'transactions' => array_map(self::transactionData(...), $order->getTransactions()),
            'history' => array_map(static fn($item) => ['id' => $item->id, 'prevStatusId' => $item->prevStatusId, 'newStatusId' => $item->newStatusId, 'message' => $item->message, 'dateCreated' => $item->dateCreated?->format(DATE_ATOM)], app(OrderHistories::class)->getAllOrderHistoriesByOrderId($orderId)),
            'adjustments' => array_map(static fn($item) => ['id' => $item->id, 'type' => $item->type, 'name' => $item->name, 'description' => $item->description, 'amount' => $item->amount, 'included' => $item->included, 'lineItemId' => $item->lineItemId], $order->getAdjustments()),
            'fulfillment' => app(Inventory::class)->getInventoryFulfillmentLevels($order)->map(static fn($level) => ['inventoryItemId' => $level->inventoryItemId, 'inventoryLocationId' => $level->inventoryLocationId, 'lineItemId' => $level->lineItemId, 'outstandingCommittedQuantity' => $level->outstandingCommittedQuantity])->all(),
            default => throw new InvalidArgumentException('Unknown order relation.'),
        };
        return ['count' => count(array_slice($items, $offset, $limit)), 'items' => array_values(array_slice($items, $offset, $limit)), 'nextOffset' => $offset + $limit < count($items) ? $offset + $limit : null];
    }

    public function setLineItem(CraftUser $actor, int $orderId, int $quantity, ?int $lineItemId = null, ?int $purchasableId = null, array $options = [], ?string $note = null): Order
    {
        $this->find($actor, $orderId, 'save');
        if (($lineItemId === null) === ($purchasableId === null) || $quantity < 0) {
            throw new InvalidArgumentException('Provide exactly one of lineItemId and purchasableId, and a nonnegative quantity.');
        }
        return Cache::lock("commerce:order-management:$orderId", 30)->block(5, function() use ($actor, $orderId, $quantity, $lineItemId, $purchasableId, $options, $note): Order {
            $order = $this->find($actor, $orderId, 'save');
            if ($lineItemId !== null) {
                $item = collect($order->getLineItems())->firstWhere('id', $lineItemId);
                if (!$item) {
                    throw new InvalidArgumentException('Resource not found.');
                }
            } else {
                $purchasable = app(Purchasables::class)->getPurchasableById($purchasableId);
                if (!$purchasable || !Gate::forUser($actor)->allows('view', $purchasable)) {
                    throw new InvalidArgumentException('Resource not found.');
                }
                $item = app(LineItems::class)->resolveLineItem($order, $purchasableId, $options);
            }
            if ($quantity === 0) {
                $order->removeLineItem($item);
            } else {
                $item->qty = $quantity;
                if ($note !== null) {
                    $item->note = $note;
                }
                $order->addLineItem($item);
            }
            $order->setRecalculationMode($order->isCompleted ? Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY : Order::RECALCULATION_MODE_ALL);
            return $this->save($actor, $order);
        });
    }

    public function status(CraftUser $actor, int $orderId, int $statusId, ?string $message = null): Order
    {
        $order = $this->find($actor, $orderId, 'save');
        if (!$order->isCompleted || !app(OrderStatuses::class)->getOrderStatusById($statusId, $order->storeId)) {
            throw new InvalidArgumentException('A completed order and a status from its store are required.');
        }
        $order->orderStatusId = $statusId;
        if ($message !== null) {
            $order->message = $message;
        }
        return $this->save($actor, $order);
    }

    public function complete(CraftUser $actor, int $orderId): Order
    {
        $this->find($actor, $orderId, 'save');
        return Cache::lock("commerce:order-management:$orderId", 30)->block(5, function() use ($actor, $orderId): Order {
            $order = $this->find($actor, $orderId, 'save');
            if (!$order->isCompleted && !$order->markAsComplete()) {
                throw ValidationException::withMessages($order->getErrors() ?: ['order' => 'Order could not be completed.']);
            }
            return $order;
        });
    }

    public function recalculate(CraftUser $actor, int $orderId): Order
    {
        $order = $this->find($actor, $orderId, 'save');
        $order->setRecalculationMode($order->isCompleted ? Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY : Order::RECALCULATION_MODE_ALL);
        return $this->save($actor, $order);
    }

    public function sendEmail(CraftUser $actor, int $orderId, int $emailId): array
    {
        $order = $this->find($actor, $orderId, 'save');
        $email = app(Emails::class)->getEmailById($emailId, $order->storeId);
        if (!$email || !$email->enabled) {
            throw new InvalidArgumentException('Resource not found.');
        }
        if (!app(Emails::class)->sendEmail($email, $order)) {
            throw new InvalidArgumentException('The email could not be sent.');
        }
        return ['sent' => true];
    }

    public function fulfill(CraftUser $actor, int $orderId, int $lineItemId, int $locationId, int $quantity): array
    {
        $this->find($actor, $orderId, 'save');
        return Cache::lock("commerce:order-management:$orderId", 30)->block(5, function() use ($actor, $orderId, $lineItemId, $locationId, $quantity): array {
            $order = $this->find($actor, $orderId, 'save');
            if (!$order->isCompleted || $quantity < 1) {
                throw new InvalidArgumentException('A completed order and positive quantity are required.');
            }
            $lineItem = collect($order->getLineItems())->firstWhere('id', $lineItemId);
            $location = app(InventoryLocations::class)->getInventoryLocationById($locationId);
            if (!$lineItem || !$location) {
                throw new InvalidArgumentException('Resource not found.');
            }
            $levels = app(Inventory::class)->getInventoryFulfillmentLevels($order);
            $level = $levels->first(fn($level) => $level->lineItemId === $lineItemId && $level->inventoryLocationId === $locationId);
            if (!$level || $quantity > $level->outstandingCommittedQuantity) {
                throw new InvalidArgumentException('Quantity exceeds the outstanding commitment for this line item and location.');
            }
            $movement = new InventoryFulfillMovement(['inventoryItemId' => $level->inventoryItemId, 'lineItemId' => $lineItemId, 'fromInventoryLocation' => $location, 'toInventoryLocation' => $location, 'fromInventoryTransactionType' => InventoryTransactionType::COMMITTED, 'toInventoryTransactionType' => InventoryTransactionType::FULFILLED, 'quantity' => $quantity, 'userId' => $actor->getCraftUserId()]);
            if (!$movement->validate()) {
                throw ValidationException::withMessages($movement->getErrors());
            }
            if (!app(Inventory::class)->executeInventoryMovements(new InventoryMovementCollection([$movement]))) {
                throw new InvalidArgumentException('Inventory could not be fulfilled.');
            }
            return $this->related($actor, $orderId, 'fulfillment');
        });
    }

    private function save(CraftUser $actor, Order $order): Order
    {
        Gate::forUser($actor)->authorize('save', $order);
        $result = app(UserInitiatedElementSave::class)->save($order, $actor);
        if (!$result->successful) {
            throw ValidationException::withMessages($result->element->getErrors());
        }
        return $order;
    }

    /** Only bookkeeping fields, never gateway responses, tokens, hashes or references. */
    public static function transactionData(Transaction $transaction): array
    {
        return ['id' => $transaction->id, 'orderId' => $transaction->orderId, 'parentId' => $transaction->parentId, 'gatewayId' => $transaction->gatewayId, 'type' => $transaction->type, 'status' => $transaction->status, 'amount' => $transaction->amount, 'currency' => $transaction->currency, 'paymentAmount' => $transaction->paymentAmount, 'paymentCurrency' => $transaction->paymentCurrency, 'dateCreated' => $transaction->dateCreated?->format(DATE_ATOM)];
    }
}
