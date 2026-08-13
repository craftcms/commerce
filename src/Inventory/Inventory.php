<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory;

use craft\commerce\base\Purchasable;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use craft\commerce\elements\Order;
use craft\commerce\events\InventoryMovementEvent;
use craft\commerce\events\UpdateInventoryLevelEvent;
use craft\commerce\models\inventory\InventoryCommittedMovement;
use craft\commerce\models\inventory\InventoryManualMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\inventory\UpdateInventoryLevelInTransfer;
use craft\commerce\Plugin;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Models\InventoryFulfillmentLevel;
use CraftCms\Commerce\Inventory\Models\InventoryItem;
use CraftCms\Commerce\Inventory\Models\InventoryLevel;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;
use CraftCms\Commerce\Inventory\Models\InventoryTransaction;
use CraftCms\Commerce\Inventory\Records\InventoryItem as InventoryItemRecord;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\Models\OrderNotice;
use CraftCms\Commerce\Purchasable\Elements\Purchasable as NewPurchasable;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use function CraftCms\Cms\t;

#[Singleton]
class Inventory
{
    public const string EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL = 'afterExecuteUpdateInventoryLevel';

    public const string EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT = 'afterExecuteInventoryMovement';

    /**
     * @return Collection<int, InventoryLevel>
     */
    public function getInventoryLevelsForPurchasable(Purchasable|NewPurchasable $purchasable): Collection
    {
        $inventoryLevels = collect();

        if (!$purchasable->id) {
            return $inventoryLevels;
        }

        // Self-heal a missing inventory item id so callers get accurate levels
        // even when the purchasable was loaded before its row was created.
        if (!$purchasable->inventoryItemId && $purchasable::hasInventory()) {
            $this->getInventoryItemByPurchasable($purchasable);
        }

        if (!$purchasable->inventoryItemId) {
            return $inventoryLevels;
        }

        $storeId = $purchasable->getStore()->id;
        $storeInventoryLocations = app(InventoryLocations::class)->getInventoryLocations($storeId);

        foreach ($storeInventoryLocations as $inventoryLocation) {
            $inventoryLevel = $this->getInventoryLevel($purchasable->inventoryItemId, $inventoryLocation->id);

            if (!$inventoryLevel) {
                continue;
            }
            $inventoryLevels->push($inventoryLevel);
        }

        return $inventoryLevels;
    }

    public function getInventoryItemByPurchasable(Purchasable|NewPurchasable $purchasable): InventoryItem
    {
        // Self-heal: if the purchasable has somehow ended up without an associated
        // inventory item (e.g. due to a draft-apply or duplicate path that didn't
        // create one), find or create one before returning.
        if (!$purchasable->inventoryItemId && $purchasable->id) {
            $record = $this->ensureInventoryItemRecord($purchasable);
            if ($record) {
                $purchasable->inventoryItemId = $record->id;
            }
        }

        return $this->getInventoryItemById($purchasable->inventoryItemId);
    }

    /**
     * Finds or creates the inventory item record for the given purchasable, always
     * keyed by its canonical id so drafts and revisions resolve to the same row as
     * their canonical. Returns null if the purchasable type does not track inventory
     * or there is no canonical id yet.
     */
    public function ensureInventoryItemRecord(Purchasable|NewPurchasable $purchasable): ?InventoryItemRecord
    {
        if (!$purchasable::hasInventory()) {
            return null;
        }

        $canonicalId = $purchasable->getCanonicalId();
        if (!$canonicalId) {
            return null;
        }

        $record = InventoryItemRecord::where('purchasableId', $canonicalId)->first();

        if (!$record) {
            $record = new InventoryItemRecord();
            $record->purchasableId = $canonicalId;
            $record->countryCodeOfOrigin = '';
            $record->administrativeAreaCodeOfOrigin = '';
            $record->harmonizedSystemCode = '';
            $record->save();
        }

        return $record;
    }

    public function getInventoryItemById(int $id): InventoryItem
    {
        $inventoryItem = $this->getInventoryItemQuery()
            ->where('id', $id)
            ->first();

        return $this->populateInventoryItem((array) $inventoryItem);
    }

    /**
     * @param int[] $ids
     * @return Collection<int, InventoryItem>
     */
    public function getInventoryItemsByIds(array $ids): Collection
    {
        $inventoryItemsResults = $this->getInventoryItemQuery()
            ->whereIn('id', $ids)
            ->get();

        $inventoryItems = collect();
        foreach ($inventoryItemsResults as $inventoryItem) {
            $inventoryItems->push($this->populateInventoryItem((array) $inventoryItem));
        }

        return $inventoryItems;
    }

    /**
     * Returns an inventory level model which is the sum of all inventory movements types for an item in a location.
     */
    public function getInventoryLevel(InventoryItem|int $inventoryItem, InventoryLocation|int $inventoryLocation, bool $withTrashed = false): ?InventoryLevel
    {
        $inventoryItemId = $inventoryItem instanceof InventoryItem ? $inventoryItem->id : $inventoryItem;
        $inventoryLocationId = $inventoryLocation instanceof InventoryLocation ? $inventoryLocation->id : $inventoryLocation;

        $result = $this->getInventoryLevelQuery(withTrashed: $withTrashed, inventoryLocationId: $inventoryLocationId)
            ->where('it.inventoryLocationId', $inventoryLocationId)
            ->where('it.inventoryItemId', $inventoryItemId)
            ->first();

        if (!$result) {
            return null;
        }

        return $this->populateInventoryLevel((array) $result);
    }

    public function saveInventoryItem(InventoryItem $inventoryItem): bool
    {
        $inventoryItemRecord = InventoryItemRecord::find($inventoryItem->id);

        if ($inventoryItemRecord === null) {
            throw new RuntimeException('No inventory item exists with the ID “' . $inventoryItem->id . '”');
        }

        $inventoryItemRecord->purchasableId = $inventoryItem->purchasableId;
        $inventoryItemRecord->countryCodeOfOrigin = $inventoryItem->countryCodeOfOrigin;
        $inventoryItemRecord->administrativeAreaCodeOfOrigin = $inventoryItem->administrativeAreaCodeOfOrigin;
        $inventoryItemRecord->harmonizedSystemCode = $inventoryItem->harmonizedSystemCode;

        return $inventoryItemRecord->save();
    }

    private function populateInventoryItem(array $data): InventoryItem
    {
        return new InventoryItem($data);
    }

    private function populateInventoryTransaction(array $data): InventoryTransaction
    {
        return new InventoryTransaction($data);
    }

    private function populateInventoryLevel(array $data): InventoryLevel
    {
        unset($data['purchasableId']);
        return new InventoryLevel($data);
    }

    private function populateInventoryFulfillmentLevel(array $data): InventoryFulfillmentLevel
    {
        return new InventoryFulfillmentLevel($data);
    }

    /**
     * @return Collection<int, InventoryLevel>
     */
    public function getInventoryLocationLevels(InventoryLocation $inventoryLocation, bool $withTrashed = false): Collection
    {
        $levels = $this->getInventoryLevelQuery(withTrashed: $withTrashed, inventoryLocationId: $inventoryLocation->id)
            ->where('it.inventoryLocationId', $inventoryLocation->id)
            ->whereNotNull('elements.id')
            ->get();

        $inventoryItems = $this->getInventoryItemsByIds($levels->pluck('inventoryItemId')->unique()->toArray());

        return $levels->map(function($level) use ($inventoryItems) {
            $inventoryLevel = $this->populateInventoryLevel((array) $level);
            if ($item = $inventoryItems->firstWhere('id', $level->inventoryItemId)) {
                $inventoryLevel->setInventoryItem($item);
            }
            return $inventoryLevel;
        });
    }

    /**
     * Returns the totals for inventory items grouped by location and purchasable/inventoryItem.
     */
    public function getInventoryLevelQuery(?int $limit = null, ?int $offset = null, bool $withTrashed = false, ?int $inventoryLocationId = null): Builder
    {
        $inventoryTotals = DB::table(Table::INVENTORYLOCATIONS . ' as il')
            ->select([
                'il.id as inventoryLocationId',
                'ii.id as inventoryItemId',
                'it.type as type',
            ])
            ->selectRaw('COALESCE(SUM(it.quantity), 0) as quantity')
            ->crossJoin(Table::INVENTORYITEMS . ' as ii')
            ->leftJoin(Table::INVENTORYTRANSACTIONS . ' as it', function($join) {
                $join->on('il.id', '=', 'it.inventoryLocationId')
                    ->on('ii.id', '=', 'it.inventoryItemId');
            })
            ->groupBy('il.id', 'ii.id', 'it.type');

        // Scoping the location in the subquery prevents the CROSS JOIN from expanding
        // to all locations × all items before the outer WHERE can filter it down.
        if ($inventoryLocationId !== null) {
            $inventoryTotals->where('il.id', $inventoryLocationId);
        }

        $query = DB::table(Table::INVENTORYITEMS . ' as ii')
            ->selectRaw('ii.id as inventoryItemId')
            ->selectRaw('ii.purchasableId as purchasableId')
            ->selectRaw('it.inventoryLocationId as inventoryLocationId')
            ->selectRaw("SUM(CASE WHEN it.type = 'available' THEN it.quantity ELSE 0 END) as availableTotal")
            ->selectRaw("SUM(CASE WHEN it.type = 'committed' THEN it.quantity ELSE 0 END) as committedTotal")
            ->selectRaw("SUM(CASE WHEN it.type = 'reserved' THEN it.quantity ELSE 0 END) as reservedTotal")
            ->selectRaw("SUM(CASE WHEN it.type = 'damaged' THEN it.quantity ELSE 0 END) as damagedTotal")
            ->selectRaw("SUM(CASE WHEN it.type = 'safety' THEN it.quantity ELSE 0 END) as safetyTotal")
            ->selectRaw("SUM(CASE WHEN it.type = 'qualityControl' THEN it.quantity ELSE 0 END) as qualityControlTotal")
            ->selectRaw("SUM(CASE WHEN it.type = 'incoming' THEN it.quantity ELSE 0 END) as incomingTotal")
            ->selectRaw("SUM(CASE WHEN it.type IN ('qualityControl','safety','damaged','reserved') THEN it.quantity ELSE 0 END) as unavailableTotal")
            ->selectRaw("SUM(CASE WHEN it.type IN ('qualityControl','safety','damaged','reserved','available','committed') THEN it.quantity ELSE 0 END) as onHandTotal")
            ->leftJoinSub($inventoryTotals, 'it', function($join) {
                $join->on('it.inventoryItemId', '=', 'ii.id');
            })
            ->leftJoin(CraftTable::ELEMENTS . ' as elements', function($join) {
                $join->on('ii.purchasableId', '=', 'elements.id')
                    ->whereNull('elements.draftId')
                    ->whereNull('elements.revisionId');
            })
            ->groupBy('ii.id', 'ii.purchasableId', 'it.inventoryLocationId');

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        if (!$withTrashed) {
            $query->whereNull('elements.dateDeleted');
        }

        return $query;
    }

    public function getInventoryItemQuery(): Builder
    {
        return DB::table(Table::INVENTORYITEMS)
            ->select([
                'id',
                'purchasableId',
                'countryCodeOfOrigin',
                'administrativeAreaCodeOfOrigin',
                'harmonizedSystemCode',
            ]);
    }

    public function executeUpdateInventoryLevels(UpdateInventoryLevelCollection $updateInventoryLevels): bool
    {
        if ($updateInventoryLevels->count() < 1) {
            return true;
        }

        DB::beginTransaction();

        try {
            foreach ($updateInventoryLevels as $updateInventoryLevel) {
                if ($updateInventoryLevel->updateAction === InventoryUpdateQuantityType::SET) {
                    $this->setInventoryLevel($updateInventoryLevel);
                } else {
                    $this->adjustInventoryLevel($updateInventoryLevel);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // TODO: Potentially move this to a job in the queue
        // Update all purchasables stock
        foreach ($updateInventoryLevels->getPurchasables() as $purchasable) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPurchasables()->updateStoreStockCache($purchasable, true);
        }

        // TODO: migrate event firing to Laravel once the event system is bridged
        foreach ($updateInventoryLevels as $updateInventoryLevel) {
            /** @phpstan-ignore-next-line */
            if (Plugin::getInstance()->getInventory()->hasEventHandlers(self::EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL)) {
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getInventory()->trigger(self::EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL, new UpdateInventoryLevelEvent(
                    updateInventoryLevel: $updateInventoryLevel,
                ));
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $updateInventoryLevelAttributes
     */
    public function updateInventoryLevel(int $inventoryItemId, int $quantity, array $updateInventoryLevelAttributes = []): void
    {
        $updateInventoryLevelAttributes += [
            'quantity' => $quantity,
            'updateAction' => InventoryUpdateQuantityType::SET,
            'inventoryLocationId' => app(InventoryLocations::class)->getAllInventoryLocations()->first()->id,
            'type' => InventoryTransactionType::AVAILABLE->value,
        ];

        $updateInventoryLevel = new UpdateInventoryLevel($updateInventoryLevelAttributes);
        $updateInventoryLevel->inventoryItemId = $inventoryItemId;

        $updateInventoryLevels = UpdateInventoryLevelCollection::make();
        $updateInventoryLevels->push($updateInventoryLevel);

        $this->executeUpdateInventoryLevels($updateInventoryLevels);
    }

    /**
     * @param array<string, mixed> $updateInventoryLevelAttributes
     */
    public function updatePurchasableInventoryLevel(Purchasable|NewPurchasable $purchasable, int $quantity, array $updateInventoryLevelAttributes = []): void
    {
        $inventoryLocation = $purchasable->getStore()->getInventoryLocations()->first();

        if (!$inventoryLocation) {
            // If no inventory location exists, we can't update inventory
            // TODO change method to return false or throw an exception
            return;
        }

        $updateInventoryLevelAttributes += [
            'quantity' => $quantity,
            'updateAction' => InventoryUpdateQuantityType::SET,
            'inventoryItemId' => $purchasable->inventoryItemId,
            'inventoryLocationId' => $inventoryLocation->id,
            'type' => InventoryTransactionType::AVAILABLE->value,
        ];

        $this->updateInventoryLevel($purchasable->inventoryItemId, $quantity, $updateInventoryLevelAttributes);

        // Clear the stock cache for the class instance
        unset($purchasable->stock);
    }

    private function setInventoryLevel(UpdateInventoryLevel|UpdateInventoryLevelInTransfer $updateInventoryLevel): bool
    {
        $tableName = Table::INVENTORYTRANSACTIONS;

        if ($updateInventoryLevel->type === 'onHand') {
            $types = collect(InventoryTransactionType::onHand())->pluck('value')->all();
        } else {
            $types = [$updateInventoryLevel->type];
        }

        $quantityQuery = DB::table($tableName)
            ->whereIn('type', $types)
            ->where('inventoryItemId', $updateInventoryLevel->inventoryItemId)
            ->where('inventoryLocationId', $updateInventoryLevel->inventoryLocationId)
            ->selectRaw('? - COALESCE(SUM(quantity), 0) as remaining', [$updateInventoryLevel->quantity])
            ->value('remaining');

        $type = $updateInventoryLevel->type;
        if ($updateInventoryLevel->type === 'onHand') {
            $type = InventoryTransactionType::AVAILABLE->value;
        }

        $data = [
            'quantity' => $quantityQuery,
            'type' => $type,
            'inventoryItemId' => $updateInventoryLevel->inventoryItemId,
            'inventoryLocationId' => $updateInventoryLevel->inventoryLocationId,
            'note' => $updateInventoryLevel->note,
            'movementHash' => $this->getMovementHash(),
            'dateCreated' => now()->toDateTimeString(),
            'userId' => request()->craftUser()?->id,
        ];

        if ($updateInventoryLevel instanceof UpdateInventoryLevelInTransfer) {
            $data['transfer'] = $updateInventoryLevel->transferId;
        }

        DB::table($tableName)->insert($data);

        return true;
    }

    private function adjustInventoryLevel(UpdateInventoryLevel|UpdateInventoryLevelInTransfer $updateInventoryLevel): bool
    {
        $tableName = Table::INVENTORYTRANSACTIONS;

        $type = $updateInventoryLevel->type;
        if ($updateInventoryLevel->type === 'onHand') {
            $type = 'available';
        }

        DB::table($tableName)->insert([
            'quantity' => $updateInventoryLevel->quantity,
            'type' => $type,
            'inventoryItemId' => $updateInventoryLevel->inventoryItemId,
            'inventoryLocationId' => $updateInventoryLevel->inventoryLocationId,
            'movementHash' => $this->getMovementHash(),
            'dateCreated' => now()->toDateTimeString(),
            'note' => $updateInventoryLevel->note,
        ]);

        return true;
    }

    public function executeInventoryMovements(InventoryMovementCollection $inventoryMovements): bool
    {
        $tableName = Table::INVENTORYTRANSACTIONS;

        DB::beginTransaction();

        try {
            /** @var InventoryMovementInterface $inventoryMovement */
            foreach ($inventoryMovements as $inventoryMovement) {
                if (!$inventoryMovement->isValid()) {
                    DB::rollBack();
                    return false;
                }

                $movementDate = now()->toDateTimeString();

                $fromInsertResult = DB::table($tableName)->insert([
                    'quantity' => -$inventoryMovement->getQuantity(),
                    'type' => $inventoryMovement->getFromInventoryTransactionType()->value,
                    'inventoryItemId' => $inventoryMovement->getInventoryItem()->id,
                    'inventoryLocationId' => $inventoryMovement->getFromInventoryLocation()->id,
                    'movementHash' => $inventoryMovement->getInventoryMovementHash(),
                    'dateCreated' => $movementDate,
                    'transferId' => $inventoryMovement->getTransferId(),
                    'lineItemId' => $inventoryMovement->getLineItemId(),
                    'userId' => $inventoryMovement->getUserId(),
                    'note' => $inventoryMovement->getNote(),
                ]);

                if (!$fromInsertResult) {
                    DB::rollBack();
                    return false;
                }

                $toInsertResult = DB::table($tableName)->insert([
                    'quantity' => $inventoryMovement->getQuantity(),
                    'type' => $inventoryMovement->getToInventoryTransactionType()->value,
                    'inventoryItemId' => $inventoryMovement->getInventoryItem()->id,
                    'inventoryLocationId' => $inventoryMovement->getToInventoryLocation()->id,
                    'movementHash' => $inventoryMovement->getInventoryMovementHash(),
                    'dateCreated' => $movementDate,
                    'transferId' => $inventoryMovement->getTransferId(),
                    'lineItemId' => $inventoryMovement->getLineItemId(),
                    'userId' => $inventoryMovement->getUserId(),
                    'note' => $inventoryMovement->getNote(),
                ]);

                if (!$toInsertResult) {
                    DB::rollBack();
                    return false;
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // TODO: Potentially move this to a job in the queue
        foreach ($inventoryMovements as $inventoryMovement) {
            // Update all purchasables stock
            $purchasable = $inventoryMovement->getInventoryItem()->getPurchasable();
            if ($purchasable) {
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getPurchasables()->updateStoreStockCache($purchasable, true);
            }
        }

        // TODO: migrate event firing to Laravel once the event system is bridged
        foreach ($inventoryMovements as $inventoryMovement) {
            /** @phpstan-ignore-next-line */
            if (Plugin::getInstance()->getInventory()->hasEventHandlers(self::EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT)) {
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getInventory()->trigger(self::EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT, new InventoryMovementEvent(
                    inventoryMovement: $inventoryMovement,
                ));
            }
        }

        return true;
    }

    public function getMovementHash(): string
    {
        return md5(uniqid((string) mt_rand(), true));
    }

    /** @return Order[] */
    public function getUnfulfilledOrders(InventoryItem|int $inventoryItem, InventoryLocation|int $inventoryLocation): array
    {
        $inventoryItemId = $inventoryItem instanceof InventoryItem ? $inventoryItem->id : $inventoryItem;
        $inventoryLocationId = $inventoryLocation instanceof InventoryLocation ? $inventoryLocation->id : $inventoryLocation;

        $inventoryLevel = $this->getInventoryLevel($inventoryItemId, $inventoryLocationId);

        if ($inventoryLevel->committedTotal <= 0) {
            return [];
        }

        // Get orders that have line items for this inventory level item
        $orderIds = DB::table(Table::LINEITEMS . ' as lineItems')
            ->select('lineItems.orderId')
            ->addSelect('lineItems.qty')
            ->leftJoin(Table::ORDERS . ' as orders', 'lineItems.orderId', '=', 'orders.id')
            ->leftJoin(Table::INVENTORYTRANSACTIONS . ' as it', 'it.lineItemId', '=', 'lineItems.id')
            ->where('orders.isCompleted', true)
            ->where('it.inventoryItemId', $inventoryItemId)
            ->where('it.inventoryLocationId', $inventoryLocationId)
            ->where('it.type', InventoryTransactionType::COMMITTED->value)
            ->groupBy('lineItems.orderId', 'lineItems.id', 'lineItems.qty')
            ->havingRaw('SUM(it.quantity) >= lineItems.qty')
            ->pluck('orderId')
            ->all();

        return Order::find()
            ->id($orderIds)
            ->all();
    }

    public function getTransactionQuery(): Builder
    {
        return DB::table(Table::INVENTORYTRANSACTIONS)
            ->select([
                'inventoryLocationId',
                'inventoryItemId',
                'movementHash',
                'quantity',
                'type',
                'note',
                'transferId',
                'lineItemId',
                'userId',
                'dateCreated',
            ])
            ->orderByDesc('dateCreated');
    }

    /**
     * @return Collection<int, InventoryTransaction>
     */
    public function getInventoryTransactions(InventoryItem $inventoryItem, InventoryLocation $inventoryLocation): Collection
    {
        $transactions = $this->getTransactionQuery()
            ->where('inventoryItemId', $inventoryItem->id)
            ->where('inventoryLocationId', $inventoryLocation->id)
            ->get();

        return $transactions->map(fn($transaction) => $this->populateInventoryTransaction((array) $transaction));
    }

    /**
     * @return Collection<int, InventoryFulfillmentLevel>
     */
    public function getInventoryFulfillmentLevels(Order $order): Collection
    {
        // We don't limit this to the order's store locations since we want to show
        // all locations that have historical inventory for the order.
        $locations = app(InventoryLocations::class)->getAllInventoryLocations();

        $inventoryFulfillmentLevels = [];
        foreach ($locations as $location) {
            $data = DB::table(Table::INVENTORYTRANSACTIONS . ' as it')
                ->selectRaw('it.lineItemId, it.inventoryItemId, it.inventoryLocationId')
                ->selectRaw(
                    "SUM(CASE WHEN ((it.type = ? AND quantity > 0) OR (it.type = ? AND quantity < 0)) THEN quantity ELSE 0 END) AS committedQuantity",
                    [InventoryTransactionType::COMMITTED->value, InventoryTransactionType::FULFILLED->value],
                )
                ->selectRaw("SUM(CASE WHEN it.type = ? THEN quantity ELSE 0 END) AS outstandingCommittedQuantity", [InventoryTransactionType::COMMITTED->value])
                ->selectRaw("SUM(CASE WHEN it.type = ? THEN quantity ELSE 0 END) AS fulfilledQuantity", [InventoryTransactionType::FULFILLED->value])
                ->join(Table::LINEITEMS . ' as li', 'li.id', '=', 'it.lineItemId')
                ->where('li.orderId', $order->id)
                ->where('it.inventoryLocationId', $location->id)
                ->where(function($query) {
                    $query->where('it.type', InventoryTransactionType::COMMITTED->value)
                        ->orWhere('it.type', InventoryTransactionType::FULFILLED->value);
                })
                ->groupBy('it.lineItemId', 'it.inventoryItemId', 'it.inventoryLocationId')
                ->get();

            foreach ($data as $row) {
                $inventoryFulfillmentLevels[] = $this->populateInventoryFulfillmentLevel((array) $row);
            }
        }

        return collect($inventoryFulfillmentLevels);
    }

    public function orderCompleteHandler(Order $order): void
    {
        /** @var array<int, Collection<int, InventoryLevel>> $allInventoryLevels */
        $allInventoryLevels = [];
        $qtyLineItem = [];
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->type === LineItemType::Custom) {
                // Skip custom line items
                continue;
            }

            $purchasable = $lineItem->getPurchasable();
            // Don't reduce stock of unlimited items.

            if (!$purchasable::hasInventory()) {
                continue;
            }

            if ($purchasable->inventoryTracked) {
                if (!isset($qtyLineItem[$purchasable->id])) {
                    $qtyLineItem[$purchasable->id] = 0;
                }
                $qtyLineItem[$purchasable->id] += $lineItem->qty;
                $allInventoryLevels[$purchasable->id] = $purchasable->getInventoryLevels();
            }
        }

        $selectedInventoryLevelForItem = [];
        /**
         * @var int $purchasableId
         * @var Collection<int, InventoryLevel> $inventoryLevels
         */
        foreach ($allInventoryLevels as $purchasableId => $inventoryLevels) {
            foreach ($inventoryLevels as $level) {
                if (!isset($selectedInventoryLevelForItem[$purchasableId])) {
                    $selectedInventoryLevelForItem[$purchasableId] = $level;

                    if ($level->availableTotal >= $qtyLineItem[$purchasableId]) {
                        break;
                    }
                    continue;
                }

                if ($level->availableTotal >= $qtyLineItem[$purchasableId]) {
                    $selectedInventoryLevelForItem[$purchasableId] = $level;
                    break;
                }
            }
        }

        $movements = InventoryMovementCollection::make();

        $reserveAmountByPurchasableId = [];
        $availableTotalByPurchasableIdAndLocationId = [];

        // Loop through line items and create committed movements for the selected inventory location
        foreach ($order->getLineItems() as $lineItem) {
            if (isset($selectedInventoryLevelForItem[$lineItem->purchasableId])) {
                $level = $selectedInventoryLevelForItem[$lineItem->purchasableId];

                if (!isset($reserveAmountByPurchasableId[$lineItem->purchasableId])) {
                    $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId] = $level->availableTotal;
                    $reserveAmountByPurchasableId[$lineItem->purchasableId] = [];
                }

                if ($lineItem->qty > $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId]) {
                    $totalToReserveForLineItem = $lineItem->qty - $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId];
                    $reserveAmountByPurchasableId[$lineItem->purchasableId][$lineItem->id] = $totalToReserveForLineItem;
                    $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId] = 0;
                } else {
                    $availableTotalByPurchasableIdAndLocationId[$lineItem->purchasableId . '-' . $level->inventoryLocationId] -= $lineItem->qty;
                }

                $inventoryCommittedMovement = new InventoryCommittedMovement();
                $inventoryCommittedMovement->inventoryItemId = $level->inventoryItemId;
                $inventoryCommittedMovement->fromInventoryLocation = $level->getInventoryLocation();
                $inventoryCommittedMovement->toInventoryLocation = $level->getInventoryLocation();
                $inventoryCommittedMovement->fromInventoryTransactionType = InventoryTransactionType::AVAILABLE;
                $inventoryCommittedMovement->toInventoryTransactionType = InventoryTransactionType::COMMITTED;
                $inventoryCommittedMovement->quantity = $lineItem->qty;
                $inventoryCommittedMovement->lineItemId = $lineItem->id;

                $movements->push($inventoryCommittedMovement);
            }
        }

        // Loop through reserve amounts to reserve the remaining stock in the other inventory locations
        foreach ($reserveAmountByPurchasableId as $purchasableId => $r) {
            foreach ($r as $lineItemId => $qty) {
                foreach ($allInventoryLevels[$purchasableId] as $level) {
                    if ($level === $selectedInventoryLevelForItem[$purchasableId]) {
                        continue;
                    }

                    if (!isset($availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId])) {
                        $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId] = $level->availableTotal;
                    }

                    $canReserveFullQty = $qty <= $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId];
                    $qtyToReserve = $canReserveFullQty ? $qty : $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId];

                    if ($qtyToReserve < 1) {
                        break;
                    }

                    $availableTotalByPurchasableIdAndLocationId[$purchasableId . '-' . $level->inventoryLocationId] -= $qtyToReserve;

                    $inventoryManualMovement = new InventoryManualMovement();
                    $inventoryManualMovement->inventoryItemId = $level->inventoryItemId;
                    $inventoryManualMovement->fromInventoryLocation = $level->getInventoryLocation();
                    $inventoryManualMovement->toInventoryLocation = $level->getInventoryLocation();
                    $inventoryManualMovement->fromInventoryTransactionType = InventoryTransactionType::AVAILABLE;
                    $inventoryManualMovement->toInventoryTransactionType = InventoryTransactionType::RESERVED;
                    $inventoryManualMovement->quantity = $qtyToReserve;
                    $inventoryManualMovement->lineItemId = $lineItemId;

                    $movements->push($inventoryManualMovement);

                    $qty -= $qtyToReserve;
                    if ($qty <= 0) {
                        break;
                    }
                }
            }
        }

        $this->executeInventoryMovements($movements);

        foreach ($selectedInventoryLevelForItem as $key => $inventoryLevel) {
            /** @phpstan-ignore-next-line */
            if ($purchasable = Elements::getElementById($key)) {
                if ($purchasable instanceof Purchasable || $purchasable instanceof NewPurchasable) {
                    /** @phpstan-ignore-next-line */
                    Plugin::getInstance()->getPurchasables()->updateStoreStockCache($purchasable, true);

                    // If the purchasable doesn't allow out of stock purchases, check whether the movement
                    // pushed available stock below zero (e.g. due to concurrent orders).
                    if (!$purchasable->allowOutOfStockPurchases) {
                        $freshLevel = $this->getInventoryLevel($inventoryLevel->inventoryItemId, $inventoryLevel->inventoryLocationId);
                        if ($freshLevel && $freshLevel->availableTotal < 0) {
                            $notice = new OrderNotice([
                                'type' => 'inventoryBelowZero',
                                'attribute' => 'lineItems',
                                'message' => t('Available inventory for "{description}" has gone below zero.', [
                                    'description' => $purchasable->getDescription(),
                                ], category: 'commerce'),
                                'noticeType' => OrderNoticeType::Admin,
                            ]);
                            $order->addNotice($notice);
                        }
                    }
                }
            }
        }
    }
}
