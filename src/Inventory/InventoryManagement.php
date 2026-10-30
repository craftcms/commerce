<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory;

use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\InventoryManualMovement;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Management\Access;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

readonly class InventoryManagement
{
    public function __construct(private Access $access, private Inventory $inventory, private InventoryLocations $locations)
    {
    }

    public function levels(CraftUser $actor, int $locationId, ?int $itemId = null, int $limit = 100, int $offset = 0): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $this->location($locationId);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Use limit 1-100 and a nonnegative offset.');
        }
        $query = $this->inventory->getInventoryLevelQuery($limit + 1, $offset, inventoryLocationId: $locationId)->orderBy('ii.id');
        if ($itemId !== null) {
            $query->where('ii.id', $itemId);
        }
        $rows = $query->get();

        return ['count' => min($rows->count(), $limit), 'levels' => $rows->take($limit)->map(fn($row) => (array)$row)->all(), 'limit' => $limit, 'offset' => $offset, 'nextOffset' => $rows->count() > $limit ? $offset + $limit : null];
    }

    public function history(CraftUser $actor, int $locationId, int $itemId, int $limit = 100, int $offset = 0): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $this->location($locationId);
        $this->item($itemId);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Use limit 1-100 and a nonnegative offset.');
        }
        $rows = $this->inventory->getTransactionQuery()->where('inventoryLocationId', $locationId)->where('inventoryItemId', $itemId)
            ->orderByDesc('id')->offset($offset)->limit($limit + 1)
            ->get(['id', 'type', 'quantity', 'note', 'dateCreated', 'inventoryItemId', 'inventoryLocationId']);

        return ['count' => min($rows->count(), $limit), 'transactions' => $rows->take($limit)->map(fn($row) => (array)$row)->all(), 'nextOffset' => $rows->count() > $limit ? $offset + $limit : null];
    }

    public function update(CraftUser $actor, int $locationId, int $itemId, string $type, InventoryUpdateQuantityType $action, int $quantity, string $note = ''): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $this->location($locationId);
        $this->item($itemId);
        if (strlen($note) > 255) {
            throw new InvalidArgumentException('Notes must be at most 255 bytes.');
        }
        $update = new UpdateInventoryLevel(['inventoryItemId' => $itemId, 'inventoryLocationId' => $locationId, 'type' => $type, 'updateAction' => $action, 'quantity' => $quantity, 'note' => $note]);
        if (!$update->validate()) {
            throw ValidationException::withMessages($update->getErrors());
        }

        return Cache::lock("commerce:inventory-management:$locationId:$itemId", 30)->block(5, function() use ($update, $actor, $locationId, $itemId): array {
            if (!$this->inventory->executeUpdateInventoryLevels(new UpdateInventoryLevelCollection([$update]))) {
                throw new InvalidArgumentException('Inventory could not be updated.');
            }
            return $this->levels($actor, $locationId, $itemId);
        });
    }

    public function move(CraftUser $actor, int $locationId, int $itemId, InventoryTransactionType $from, InventoryTransactionType $to, int $quantity, string $note = ''): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $location = $this->location($locationId);
        $this->item($itemId);
        if ($quantity < 1 || strlen($note) > 255) {
            throw new InvalidArgumentException('Use a positive quantity and a note of at most 255 bytes.');
        }

        return Cache::lock("commerce:inventory-management:$locationId:$itemId", 30)->block(5, function() use ($actor, $location, $itemId, $from, $to, $quantity, $note): array {
            $movement = new InventoryManualMovement(['inventoryItemId' => $itemId, 'fromInventoryLocation' => $location, 'toInventoryLocation' => $location, 'fromInventoryTransactionType' => $from, 'toInventoryTransactionType' => $to, 'quantity' => $quantity, 'note' => $note, 'userId' => $actor->getCraftUserId()]);
            if (!$movement->validate()) {
                throw ValidationException::withMessages($movement->getErrors());
            }
            if (!$this->inventory->executeInventoryMovements(new InventoryMovementCollection([$movement]))) {
                throw new InvalidArgumentException('Inventory could not be moved.');
            }
            return $this->levels($actor, $location->id, $itemId);
        });
    }

    private function location(int $id): Data\InventoryLocation
    {
        return $this->locations->getInventoryLocationById($id) ?? throw new InvalidArgumentException('Resource not found.');
    }

    private function item(int $id): void
    {
        if (!$this->inventory->getInventoryItemQuery()->where('id', $id)->exists()) {
            throw new InvalidArgumentException('Resource not found.');
        }
    }
}
