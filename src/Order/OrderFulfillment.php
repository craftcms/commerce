<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use CraftCms\Cms\Support\Facades\Activities;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Data\InventoryFulfillmentLevel;
use CraftCms\Commerce\Inventory\Data\InventoryFulfillMovement;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Activity\OrderFulfilled;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Exceptions\FulfillmentException;
use Illuminate\Support\Collection;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Moves an order's committed stock to fulfilled, and back.
 *
 * @since 6.0.0
 */
final readonly class OrderFulfillment
{
    public function __construct(
        private Inventory $inventory,
        private InventoryLocations $locations,
    ) {
    }

    /**
     * The order's committed and fulfilled stock, by line item and location.
     *
     * @return Collection<int, InventoryFulfillmentLevel>
     */
    public function levels(Order $order): Collection
    {
        return $order->isCompleted ? $this->inventory->getInventoryFulfillmentLevels($order)->values() : collect();
    }

    public function hasOutstandingStock(Order $order): bool
    {
        return $this->levels($order)->contains(fn(InventoryFulfillmentLevel $level) => $level->outstandingCommittedQuantity > 0);
    }

    /**
     * The key a level goes by in a submitted form.
     */
    public static function levelKey(InventoryFulfillmentLevel $level): string
    {
        return "{$level->inventoryLocationId}-{$level->inventoryItemId}-{$level->lineItemId}";
    }

    /**
     * @param list<array{inventoryLocationId: int, inventoryItemId: int, lineItemId: int, quantity: int}> $fulfillments
     * @param Order|null $order The order the stock belongs to, which has the change recorded in its activity.
     * @throws FulfillmentException
     */
    public function fulfill(array $fulfillments, ?Order $order = null): void
    {
        $movements = [];
        $recorded = [];
        $descriptions = [];

        foreach ($order?->getLineItems() ?? [] as $lineItem) {
            $descriptions[$lineItem->id] = $lineItem->getDescription();
        }

        foreach ($fulfillments as $fulfillment) {
            $qty = (int)$fulfillment['quantity'];

            if ($qty === 0) {
                continue;
            }

            $inventoryLocation = $this->locations->getInventoryLocationById((int)$fulfillment['inventoryLocationId']);

            $movement = new InventoryFulfillMovement();
            $movement->fromInventoryLocation = $inventoryLocation;
            $movement->fromInventoryTransactionType = InventoryTransactionType::COMMITTED;
            $movement->toInventoryLocation = $inventoryLocation;
            $movement->toInventoryTransactionType = InventoryTransactionType::FULFILLED;
            $movement->inventoryItemId = (int)$fulfillment['inventoryItemId'];
            $movement->lineItemId = (int)$fulfillment['lineItemId'];
            $movement->quantity = $qty;
            $movement->userId = currentUser()?->getCraftUserId();
            $movements[] = $movement;
            $recorded[] = [
                'item' => (string)($descriptions[$movement->lineItemId] ?? ''),
                'location' => (string)$inventoryLocation->name,
                'quantity' => $qty,
            ];
        }

        foreach ($movements as $movement) {
            if (!$movement->isValid()) {
                throw new FulfillmentException(
                    t('Invalid inventory movements.', category: 'commerce'),
                    ['fulfillment' => $movement->getErrors()],
                );
            }
        }

        if (!$this->inventory->executeInventoryMovements(InventoryMovementCollection::make($movements))) {
            throw new FulfillmentException(t('Invalid inventory movements.', category: 'commerce'));
        }

        if ($order && $recorded !== []) {
            Activities::record(new OrderFulfilled($order, $recorded));
        }
    }
}
