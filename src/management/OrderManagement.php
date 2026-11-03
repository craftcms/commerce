<?php

declare(strict_types=1);

namespace craft\commerce\management;

use Craft;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\elements\Order;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\models\inventory\InventoryFulfillMovement;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\elements\User;
use InvalidArgumentException;

readonly class OrderManagement
{
    public function find(User $actor, int $id, string $ability = 'view'): Order
    {
        $order = Order::find()->id($id)->status(null)->one();
        if (!$order || !Access::allows($actor, $ability, $order)) {
            throw new InvalidArgumentException('Resource not found.');
        }
        return $order;
    }

    public function related(User $actor, int $orderId, string $relation, int $limit = 100, int $offset = 0): array
    {
        $order = $this->find($actor, $orderId);
        if (!in_array($relation, ['lineItems', 'adjustments'], true) && !Access::canAccess($actor, 'view', $order)) {
            throw new InvalidArgumentException('Resource not found.');
        }
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Use limit 1-100 and a nonnegative offset.');
        }
        $items = match ($relation) {
            'lineItems' => array_map(static fn($item) => ['id' => $item->id, 'purchasableId' => $item->purchasableId, 'sku' => $item->getSku(), 'description' => $item->getDescription(), 'qty' => $item->qty, 'price' => $item->price, 'salePrice' => $item->salePrice, 'total' => $item->getTotal(), 'note' => $item->note, 'options' => $item->getOptions(), 'lineItemStatusId' => $item->lineItemStatusId], $order->getLineItems()),
            'transactions' => array_map(self::transactionData(...), $order->getTransactions()),
            'history' => array_map(static fn($item) => ['id' => $item->id, 'prevStatusId' => $item->prevStatusId, 'newStatusId' => $item->newStatusId, 'message' => $item->message, 'dateCreated' => $item->dateCreated?->format(DATE_ATOM)], Plugin::getInstance()->getOrderHistories()->getAllOrderHistoriesByOrderId($orderId)),
            'adjustments' => array_map(static fn($item) => ['id' => $item->id, 'type' => $item->type, 'name' => $item->name, 'description' => $item->description, 'amount' => $item->amount, 'included' => $item->included, 'lineItemId' => $item->lineItemId], $order->getAdjustments()),
            'fulfillment' => Plugin::getInstance()->getInventory()->getInventoryFulfillmentLevels($order)->map(static fn($level) => ['inventoryItemId' => $level->inventoryItemId, 'inventoryLocationId' => $level->inventoryLocationId, 'lineItemId' => $level->lineItemId, 'outstandingCommittedQuantity' => $level->outstandingCommittedQuantity])->all(),
            default => throw new InvalidArgumentException('Unknown order relation.'),
        };
        return ['count' => count(array_slice($items, $offset, $limit)), 'items' => array_values(array_slice($items, $offset, $limit)), 'nextOffset' => $offset + $limit < count($items) ? $offset + $limit : null];
    }

    public function setLineItem(User $actor, int $orderId, int $quantity, ?int $lineItemId = null, ?int $purchasableId = null, array $options = [], ?string $note = null): Order
    {
        $this->find($actor, $orderId, 'updateCart');
        if (($lineItemId === null) === ($purchasableId === null) || $quantity < 0) {
            throw new InvalidArgumentException('Provide exactly one of lineItemId and purchasableId, and a nonnegative quantity.');
        }
        return Mutation::run(["commerce:order-management:$orderId", "orderComplete:$orderId"], function() use ($actor, $orderId, $quantity, $lineItemId, $purchasableId, $options, $note): Order {
            $order = $this->find($actor, $orderId, 'updateCart');
            if ($lineItemId !== null) {
                $item = collect($order->getLineItems())->firstWhere('id', $lineItemId);
                if (!$item) {
                    throw new InvalidArgumentException('Resource not found.');
                }
            } else {
                $purchasables = Plugin::getInstance()->getPurchasables();
                $customerEdit = !Access::allows($actor, 'save', $order);
                $purchasable = $customerEdit
                    ? $purchasables->getPurchasableById($purchasableId, $order->orderSiteId, $actor->id)
                    : $purchasables->getPurchasableById($purchasableId);
                if (!$purchasable || ($customerEdit
                        ? !$purchasables->isPurchasableAvailable($purchasable, $order, $actor)
                        : !Access::allows($actor, 'view', $purchasable))) {
                    throw new InvalidArgumentException('Resource not found.');
                }
                $item = Plugin::getInstance()->getLineItems()->resolveLineItem($order, $purchasableId, $options);
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
            return $this->save($actor, $order, 'updateCart');
        });
    }

    public function status(User $actor, int $orderId, int $statusId, ?string $message = null): Order
    {
        $this->find($actor, $orderId, 'save');
        return Mutation::run("commerce:order-management:$orderId", function() use ($actor, $orderId, $statusId, $message): Order {
            $order = $this->find($actor, $orderId, 'save');
            if (!$order->isCompleted || !Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($statusId, $order->storeId)) {
                throw new InvalidArgumentException('A completed order and a status from its store are required.');
            }
            $order->orderStatusId = $statusId;
            if ($message !== null) {
                $order->message = $message;
            }
            return $this->save($actor, $order);
        });
    }

    public function complete(User $actor, int $orderId): Order
    {
        $this->find($actor, $orderId, 'save');
        return Mutation::run("commerce:order-management:$orderId", function() use ($actor, $orderId): Order {
            $order = $this->find($actor, $orderId, 'save');
            if (!$order->isCompleted && !$order->markAsComplete()) {
                throw new InvalidArgumentException(implode("\n", $order->getErrorSummary(true)) ?: 'Order could not be completed.');
            }
            return $order;
        });
    }

    public function recalculate(User $actor, int $orderId): Order
    {
        $this->find($actor, $orderId, 'save');
        return Mutation::run(["commerce:order-management:$orderId", "orderComplete:$orderId"], function() use ($actor, $orderId): Order {
            $order = $this->find($actor, $orderId, 'save');
            $order->setRecalculationMode($order->isCompleted ? Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY : Order::RECALCULATION_MODE_ALL);
            return $this->save($actor, $order);
        });
    }

    public function sendEmail(User $actor, int $orderId, int $emailId): array
    {
        $order = $this->find($actor, $orderId, 'save');
        $email = Plugin::getInstance()->getEmails()->getEmailById($emailId, $order->storeId);
        if (!$email || !$email->enabled) {
            throw new InvalidArgumentException('Resource not found.');
        }
        if (!Plugin::getInstance()->getEmails()->sendEmail($email, $order)) {
            throw new InvalidArgumentException('The email could not be sent.');
        }
        return ['sent' => true];
    }

    public function fulfill(User $actor, int $orderId, int $lineItemId, int $locationId, int $quantity): array
    {
        $this->find($actor, $orderId, 'save');
        return Mutation::run("commerce:order-management:$orderId", function() use ($actor, $orderId, $lineItemId, $locationId, $quantity): array {
            $order = $this->find($actor, $orderId, 'save');
            if (!$order->isCompleted || $quantity < 1) {
                throw new InvalidArgumentException('A completed order and positive quantity are required.');
            }
            $lineItem = collect($order->getLineItems())->firstWhere('id', $lineItemId);
            $location = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($locationId);
            if (!$lineItem || !$location) {
                throw new InvalidArgumentException('Resource not found.');
            }
            $levels = Plugin::getInstance()->getInventory()->getInventoryFulfillmentLevels($order);
            $level = $levels->first(fn($level) => $level->lineItemId === $lineItemId && $level->inventoryLocationId === $locationId);
            if (!$level || $quantity > $level->outstandingCommittedQuantity) {
                throw new InvalidArgumentException('Quantity exceeds the outstanding commitment for this line item and location.');
            }
            $movement = new InventoryFulfillMovement(['inventoryItemId' => $level->inventoryItemId, 'lineItemId' => $lineItemId, 'fromInventoryLocation' => $location, 'toInventoryLocation' => $location, 'fromInventoryTransactionType' => InventoryTransactionType::COMMITTED, 'toInventoryTransactionType' => InventoryTransactionType::FULFILLED, 'quantity' => $quantity, 'userId' => $actor->id]);
            if (!$movement->validate()) {
                throw new InvalidArgumentException(implode("\n", $movement->getErrorSummary(true)));
            }
            if (!Plugin::getInstance()->getInventory()->executeInventoryMovements(new InventoryMovementCollection([$movement]))) {
                throw new InvalidArgumentException('Inventory could not be fulfilled.');
            }
            return $this->related($actor, $orderId, 'fulfillment');
        });
    }

    private function save(User $actor, Order $order, string $ability = 'save'): Order
    {
        if (!Access::allows($actor, $ability, $order)) {
            throw new InvalidArgumentException('Resource not found.');
        }
        if (!Craft::$app->getElements()->saveElement($order)) {
            throw new InvalidArgumentException(implode("\n", $order->getErrorSummary(true)));
        }
        return $order;
    }

    /** Only bookkeeping fields, never gateway responses, tokens, hashes or references. */
    public static function transactionData(Transaction $transaction): array
    {
        return ['id' => $transaction->id, 'orderId' => $transaction->orderId, 'parentId' => $transaction->parentId, 'gatewayId' => $transaction->gatewayId, 'type' => $transaction->type, 'status' => $transaction->status, 'amount' => $transaction->amount, 'currency' => $transaction->currency, 'paymentAmount' => $transaction->paymentAmount, 'paymentCurrency' => $transaction->paymentCurrency, 'dateCreated' => $transaction->dateCreated?->format(DATE_ATOM)];
    }
}
