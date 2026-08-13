<?php

namespace craft\commerce\services;

use craft\commerce\base\Purchasable;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use craft\commerce\elements\Order;
use CraftCms\Commerce\Inventory\Models\InventoryFulfillmentLevel;
use CraftCms\Commerce\Inventory\Models\InventoryItem;
use CraftCms\Commerce\Inventory\Models\InventoryLevel;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;
use CraftCms\Commerce\Inventory\Models\InventoryTransaction;
use CraftCms\Commerce\Purchasable\Elements\Purchasable as NewPurchasable;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Inventory\Inventory::class)` instead.
 */
class Inventory extends Component
{
    /**
     * @event \craft\commerce\events\UpdateInventoryLevelEvent The event that is triggered after an inventory level update is executed.
     */
    public const EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL = 'afterExecuteUpdateInventoryLevel';

    /**
     * @event \craft\commerce\events\InventoryMovementEvent The event that is triggered after an inventory movement is executed.
     */
    public const EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT = 'afterExecuteInventoryMovement';

    /**
     * @return Collection<InventoryLevel>
     */
    public function getInventoryLevelsForPurchasable(Purchasable|NewPurchasable $purchasable): Collection
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryLevelsForPurchasable($purchasable);
    }

    public function getInventoryItemByPurchasable(Purchasable|NewPurchasable $purchasable): InventoryItem
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryItemByPurchasable($purchasable);
    }

    public function ensureInventoryItemRecord(Purchasable|NewPurchasable $purchasable): ?InventoryItemRecord
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->ensureInventoryItemRecord($purchasable);
    }

    public function getInventoryItemById(int $id): InventoryItem
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryItemById($id);
    }

    /**
     * @param array<int> $ids
     * @return Collection<InventoryItem>
     */
    public function getInventoryItemsByIds(array $ids): Collection
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryItemsByIds($ids);
    }

    public function getInventoryLevel(InventoryItem|int $inventoryItem, InventoryLocation|int $inventoryLocation, bool $withTrashed = false): ?InventoryLevel
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryLevel($inventoryItem, $inventoryLocation, $withTrashed);
    }

    public function saveInventoryItem(InventoryItem $inventoryItem, bool $validate = true): bool
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->saveInventoryItem($inventoryItem);
    }

    /**
     * @return Collection<InventoryLevel>
     */
    public function getInventoryLocationLevels(InventoryLocation $inventoryLocation, bool $withTrashed = false): Collection
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryLocationLevels($inventoryLocation, $withTrashed);
    }

    public function getInventoryLevelQuery(?int $limit = null, ?int $offset = null, bool $withTrashed = false): \Illuminate\Database\Query\Builder
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryLevelQuery($limit, $offset, $withTrashed);
    }

    public function getInventoryItemQuery(): \Illuminate\Database\Query\Builder
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryItemQuery();
    }

    public function executeUpdateInventoryLevels(UpdateInventoryLevelCollection $updateInventoryLevels): bool
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->executeUpdateInventoryLevels($updateInventoryLevels);
    }

    /**
     * @param array $updateInventoryLevelAttributes
     */
    public function updateInventoryLevel(int $inventoryItemId, int $quantity, array $updateInventoryLevelAttributes = []): void
    {
        app(\CraftCms\Commerce\Inventory\Inventory::class)->updateInventoryLevel($inventoryItemId, $quantity, $updateInventoryLevelAttributes);
    }

    /**
     * @param array $updateInventoryLevelAttributes
     */
    public function updatePurchasableInventoryLevel(Purchasable|NewPurchasable $purchasable, int $quantity, array $updateInventoryLevelAttributes = []): void
    {
        app(\CraftCms\Commerce\Inventory\Inventory::class)->updatePurchasableInventoryLevel($purchasable, $quantity, $updateInventoryLevelAttributes);
    }

    public function executeInventoryMovements(InventoryMovementCollection $inventoryMovements): bool
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->executeInventoryMovements($inventoryMovements);
    }

    public function getMovementHash(): string
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getMovementHash();
    }

    public function getUnfulfilledOrders(InventoryItem|int $inventoryItem, InventoryLocation|int $inventoryLocation): array
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getUnfulfilledOrders($inventoryItem, $inventoryLocation);
    }

    public function getTransactionQuery(): \Illuminate\Database\Query\Builder
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getTransactionQuery();
    }

    /**
     * @return Collection<InventoryTransaction>
     */
    public function getInventoryTransactions(InventoryItem $inventoryItem, InventoryLocation $inventoryLocation): Collection
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryTransactions($inventoryItem, $inventoryLocation);
    }

    /**
     * @return Collection<InventoryFulfillmentLevel>
     */
    public function getInventoryFulfillmentLevels(Order $order): Collection
    {
        return app(\CraftCms\Commerce\Inventory\Inventory::class)->getInventoryFulfillmentLevels($order);
    }

    public function orderCompleteHandler(Order $order): void
    {
        app(\CraftCms\Commerce\Inventory\Inventory::class)->orderCompleteHandler($order);
    }
}
