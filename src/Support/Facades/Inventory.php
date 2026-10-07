<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getInventoryLevelsForPurchasable(\CraftCms\Commerce\Purchasable\Elements\Purchasable $purchasable)
 * @method static \CraftCms\Commerce\Inventory\Data\InventoryItem getInventoryItemByPurchasable(\CraftCms\Commerce\Purchasable\Elements\Purchasable $purchasable)
 * @method static \CraftCms\Commerce\Inventory\Models\InventoryItem|null ensureInventoryItemRecord(\CraftCms\Commerce\Purchasable\Elements\Purchasable $purchasable)
 * @method static \CraftCms\Commerce\Inventory\Data\InventoryItem getInventoryItemById(int $id)
 * @method static \Illuminate\Support\Collection getInventoryItemsByIds(int[] $ids)
 * @method static \CraftCms\Commerce\Inventory\Data\InventoryLevel|null getInventoryLevel(\CraftCms\Commerce\Inventory\Data\InventoryItem|int $inventoryItem, \CraftCms\Commerce\Inventory\Data\InventoryLocation|int $inventoryLocation, bool $withTrashed = false)
 * @method static bool saveInventoryItem(\CraftCms\Commerce\Inventory\Data\InventoryItem $inventoryItem)
 * @method static \Illuminate\Support\Collection getInventoryLocationLevels(\CraftCms\Commerce\Inventory\Data\InventoryLocation $inventoryLocation, bool $withTrashed = false)
 * @method static \Illuminate\Database\Query\Builder getInventoryLevelQuery(int|null $limit = null, int|null $offset = null, bool $withTrashed = false, int|null $inventoryLocationId = null)
 * @method static \Illuminate\Database\Query\Builder getInventoryItemQuery()
 * @method static bool executeUpdateInventoryLevels(\CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection $updateInventoryLevels)
 * @method static void updateInventoryLevel(int $inventoryItemId, int $quantity, array $updateInventoryLevelAttributes = [])
 * @method static void updatePurchasableInventoryLevel(\CraftCms\Commerce\Purchasable\Elements\Purchasable $purchasable, int $quantity, array $updateInventoryLevelAttributes = [])
 * @method static bool executeInventoryMovements(\CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection $inventoryMovements)
 * @method static string getMovementHash()
 * @method static \CraftCms\Commerce\Order\Elements\Order[] getUnfulfilledOrders(\CraftCms\Commerce\Inventory\Data\InventoryItem|int $inventoryItem, \CraftCms\Commerce\Inventory\Data\InventoryLocation|int $inventoryLocation)
 * @method static \Illuminate\Database\Query\Builder getTransactionQuery()
 * @method static \Illuminate\Support\Collection getInventoryTransactions(\CraftCms\Commerce\Inventory\Data\InventoryItem $inventoryItem, \CraftCms\Commerce\Inventory\Data\InventoryLocation $inventoryLocation)
 * @method static \Illuminate\Support\Collection getInventoryFulfillmentLevels(\CraftCms\Commerce\Order\Elements\Order $order)
 * @method static void orderCompleteHandler(\CraftCms\Commerce\Order\Elements\Order $order)
 *
 * @see \CraftCms\Commerce\Inventory\Inventory
 */
class Inventory extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Inventory\Inventory::class;
    }
}
