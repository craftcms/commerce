<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Data\InventoryFulfillmentLevel;
use CraftCms\Commerce\Inventory\Data\InventoryManualMovement;
use CraftCms\Commerce\Inventory\Data\InventoryRestockMovement;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Purchasable\Elements\Purchasable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * Keeps a completed order's committed stock in line with its line items when they're edited.
 *
 * Stock is committed when an order is completed. Editing a completed order's line items afterwards
 * commits more for the quantity added, and releases the committed stock of the quantity taken away
 * (stock that has already been fulfilled is never touched). A removed line item's committed stock is released
 * before it goes.
 *
 * @since 6.0.0
 */
final readonly class OrderStockReconciliation
{
    public function __construct(
        private Inventory $inventory,
        private InventoryLocations $locations,
    ) {
    }

    /**
     * The stock a line item has had fulfilled.
     */
    public function fulfilledQuantity(LineItem $lineItem): int
    {
        if (!$lineItem->id) {
            return 0;
        }

        return (int)DB::table(Table::INVENTORYTRANSACTIONS)
            ->where('lineItemId', $lineItem->id)
            ->where('type', InventoryTransactionType::FULFILLED->value)
            ->sum('quantity');
    }

    /**
     * The stock changes that going from one version of a completed order to another makes.
     *
     * @return list<array{lineItemId: int|null, description: string, quantity: int, short: int, removed: bool}> `quantity` is
     * what's committed (positive) or released (negative), `short` how much of what's committed isn't in stock.
     */
    public function changes(Order $before, Order $after): array
    {
        if (!$before->isCompleted || !$after->isCompleted) {
            return [];
        }

        $previous = [];
        foreach ($before->getLineItems() as $lineItem) {
            $previous[$lineItem->id] = $lineItem;
        }

        $changes = [];
        $remaining = $previous;

        foreach ($after->getLineItems() as $lineItem) {
            if (!$this->tracksStock($lineItem)) {
                continue;
            }

            $was = $lineItem->id && isset($previous[$lineItem->id]) ? $previous[$lineItem->id] : null;
            unset($remaining[$lineItem->id]);
            $quantity = $lineItem->qty - ($was ? $was->qty : 0);

            if ($quantity !== 0) {
                $changes[] = [
                    'lineItemId' => $lineItem->id,
                    'description' => $lineItem->getDescription(),
                    'quantity' => $quantity,
                    'short' => $quantity > 0 ? $this->shortBy($lineItem, $quantity) : 0,
                    'removed' => false,
                ];
            }
        }

        foreach ($remaining as $lineItem) {
            if ($this->tracksStock($lineItem) && $lineItem->qty > 0) {
                $changes[] = [
                    'lineItemId' => $lineItem->id,
                    'description' => $lineItem->getDescription(),
                    'quantity' => -$lineItem->qty,
                    'short' => 0,
                    'removed' => true,
                ];
            }
        }

        return $changes;
    }

    /**
     * Releases the stock of line items the edit removes. Do this before the order is saved, while the line
     * items still exist for the movements to refer to.
     *
     * @throws RuntimeException if the stock couldn't be moved.
     */
    public function releaseRemoved(Order $before, Order $after): void
    {
        foreach ($this->changes($before, $after) as $change) {
            if ($change['removed']) {
                $this->release($before, $change['lineItemId'], -$change['quantity']);
            }
        }
    }

    /**
     * Commits and releases stock for the other changes between two versions of a completed order. The order
     * must have been saved, so its new line items have IDs.
     *
     * @return list<string> Everything the edit did to the stock, for the order's activity.
     * @throws RuntimeException if the stock couldn't be moved.
     */
    public function reconcile(Order $before, Order $after): array
    {
        $commit = [];
        $summaries = [];

        foreach ($this->changes($before, $after) as $change) {
            if ($change['quantity'] > 0) {
                $commit[$change['lineItemId']] = $change['quantity'];
                $summaries[] = t('Committed {quantity} × {item}', ['quantity' => $change['quantity'], 'item' => $change['description']], category: 'commerce');

                continue;
            }

            $summaries[] = t('Released {quantity} × {item}', ['quantity' => -$change['quantity'], 'item' => $change['description']], category: 'commerce');

            if (!$change['removed']) {
                $this->release($after, $change['lineItemId'], -$change['quantity']);
            }
        }

        if ($commit !== []) {
            $this->inventory->commitStock($after, $commit);
        }

        return $summaries;
    }

    private function tracksStock(LineItem $lineItem): bool
    {
        if ($lineItem->type === LineItemType::Custom) {
            return false;
        }

        $purchasable = $lineItem->getPurchasable();

        return $purchasable instanceof Purchasable && $purchasable::hasInventory() && $purchasable->inventoryTracked;
    }

    private function shortBy(LineItem $lineItem, int $quantity): int
    {
        $purchasable = $lineItem->getPurchasable();

        if (!$purchasable instanceof Purchasable || $purchasable->allowOutOfStockPurchases) {
            return 0;
        }

        $available = (int)$purchasable->getInventoryLevels()->sum('availableTotal');

        return max(0, $quantity - max($available, 0));
    }

    /**
     * Returns a line item's outstanding committed stock to available, along with the reserved stock backing it.
     */
    private function release(Order $order, int $lineItemId, int $quantity): void
    {
        $movements = [];
        $remaining = $quantity;

        $levels = $this->inventory->getInventoryFulfillmentLevels($order)
            ->filter(fn(InventoryFulfillmentLevel $level) => $level->lineItemId === $lineItemId && $level->outstandingCommittedQuantity > 0);

        foreach ($levels as $level) {
            $take = min($remaining, $level->outstandingCommittedQuantity);
            $location = $this->locations->getInventoryLocationById($level->inventoryLocationId);

            $movement = new InventoryRestockMovement();
            $movement->inventoryItemId = $level->inventoryItemId;
            $movement->fromInventoryLocation = $location;
            $movement->toInventoryLocation = $location;
            $movement->fromInventoryTransactionType = InventoryTransactionType::COMMITTED;
            $movement->toInventoryTransactionType = InventoryTransactionType::AVAILABLE;
            $movement->quantity = $take;
            $movement->lineItemId = $lineItemId;
            $movement->userId = currentUser()?->getCraftUserId();
            $movements[] = $movement;

            $remaining -= $take;

            if ($remaining === 0) {
                break;
            }
        }

        $remaining = $quantity;
        $reserved = DB::table(Table::INVENTORYTRANSACTIONS)
            ->select(['inventoryItemId', 'inventoryLocationId'])
            ->selectRaw('SUM(quantity) as reservedQuantity')
            ->where('lineItemId', $lineItemId)
            ->where('type', InventoryTransactionType::RESERVED->value)
            ->groupBy('inventoryItemId', 'inventoryLocationId')
            ->havingRaw('SUM(quantity) > 0')
            ->get();

        foreach ($reserved as $row) {
            $take = min($remaining, (int)$row->reservedQuantity);
            $location = $this->locations->getInventoryLocationById((int)$row->inventoryLocationId);

            $movement = new InventoryManualMovement();
            $movement->inventoryItemId = (int)$row->inventoryItemId;
            $movement->fromInventoryLocation = $location;
            $movement->toInventoryLocation = $location;
            $movement->fromInventoryTransactionType = InventoryTransactionType::RESERVED;
            $movement->toInventoryTransactionType = InventoryTransactionType::AVAILABLE;
            $movement->quantity = $take;
            $movement->lineItemId = $lineItemId;
            $movement->userId = currentUser()?->getCraftUserId();
            $movements[] = $movement;

            $remaining -= $take;

            if ($remaining === 0) {
                break;
            }
        }

        if ($movements !== [] && !$this->inventory->executeInventoryMovements(InventoryMovementCollection::make($movements))) {
            throw new RuntimeException(t('The order’s stock couldn’t be released.', category: 'commerce'));
        }
    }
}
