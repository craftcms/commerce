<?php

declare(strict_types=1);

namespace craft\commerce\management;

use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\InventoryManualMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\InventoryLocation;
use craft\commerce\Plugin;
use craft\commerce\services\Inventory;
use craft\commerce\services\InventoryLocations;
use craft\elements\User;
use InvalidArgumentException;

readonly class InventoryManagement
{
    private Inventory $inventory;
    private InventoryLocations $locations;

    public function __construct(private Access $access)
    {
        $this->inventory = Plugin::getInstance()->getInventory();
        $this->locations = Plugin::getInstance()->getInventoryLocations();
    }

    public function levels(User $actor, int $locationId, ?int $itemId = null, int $limit = 100, int $offset = 0): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $this->location($locationId);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Use limit 1-100 and a nonnegative offset.');
        }
        $query = $this->inventory->getInventoryLevelQuery($limit + 1, $offset, inventoryLocationId: $locationId)->orderBy('ii.id');
        if ($itemId !== null) {
            $query->andWhere(['ii.id' => $itemId]);
        }
        $rows = collect($query->all());

        return ['count' => min($rows->count(), $limit), 'levels' => $rows->take($limit)->map(fn($row) => (array)$row)->all(), 'limit' => $limit, 'offset' => $offset, 'nextOffset' => $rows->count() > $limit ? $offset + $limit : null];
    }

    public function history(User $actor, int $locationId, int $itemId, int $limit = 100, int $offset = 0): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $this->location($locationId);
        $this->item($itemId);
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('Use limit 1-100 and a nonnegative offset.');
        }
        $rows = collect($this->inventory->getTransactionQuery()->where(['inventoryLocationId' => $locationId, 'inventoryItemId' => $itemId])
            ->orderBy(['id' => SORT_DESC])->offset($offset)->limit($limit + 1)
            ->select(['id', 'type', 'quantity', 'note', 'dateCreated', 'inventoryItemId', 'inventoryLocationId'])->all());

        return ['count' => min($rows->count(), $limit), 'transactions' => $rows->take($limit)->map(fn($row) => (array)$row)->all(), 'nextOffset' => $rows->count() > $limit ? $offset + $limit : null];
    }

    public function update(User $actor, int $locationId, int $itemId, string $type, InventoryUpdateQuantityType $action, int $quantity, string $note = ''): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $this->location($locationId);
        $this->item($itemId);
        if (strlen($note) > 255) {
            throw new InvalidArgumentException('Notes must be at most 255 bytes.');
        }
        $update = new UpdateInventoryLevel(['inventoryItemId' => $itemId, 'inventoryLocationId' => $locationId, 'type' => $type, 'updateAction' => $action, 'quantity' => $quantity, 'note' => $note]);
        if ($type !== 'onHand' && !in_array(InventoryTransactionType::tryFrom($type), InventoryTransactionType::allowedManualAdjustmentTypes(), true)) {
            throw new InvalidArgumentException('Choose a manual stock bucket or onHand.');
        }

        return Mutation::run("commerce:inventory-management:$locationId:$itemId", function() use ($update, $actor, $locationId, $itemId): array {
            if (!$this->inventory->executeUpdateInventoryLevels(new UpdateInventoryLevelCollection([$update]))) {
                throw new InvalidArgumentException('Inventory could not be updated.');
            }
            return $this->levels($actor, $locationId, $itemId);
        });
    }

    public function move(User $actor, int $locationId, int $itemId, InventoryTransactionType $from, InventoryTransactionType $to, int $quantity, string $note = ''): array
    {
        $this->access->permission($actor, 'commerce-manageInventoryStockLevels');
        $location = $this->location($locationId);
        $this->item($itemId);
        if ($quantity < 1 || strlen($note) > 255) {
            throw new InvalidArgumentException('Use a positive quantity and a note of at most 255 bytes.');
        }

        return Mutation::run("commerce:inventory-management:$locationId:$itemId", function() use ($actor, $location, $itemId, $from, $to, $quantity, $note): array {
            $movement = new InventoryManualMovement(['inventoryItemId' => $itemId, 'fromInventoryLocation' => $location, 'toInventoryLocation' => $location, 'fromInventoryTransactionType' => $from, 'toInventoryTransactionType' => $to, 'quantity' => $quantity, 'note' => $note, 'userId' => $actor->id]);
            if (!$movement->validate()) {
                throw new InvalidArgumentException(implode("\n", $movement->getErrorSummary(true)));
            }
            if (!$this->inventory->executeInventoryMovements(new InventoryMovementCollection([$movement]))) {
                throw new InvalidArgumentException('Inventory could not be moved.');
            }
            return $this->levels($actor, $location->id, $itemId);
        });
    }

    private function location(int $id): InventoryLocation
    {
        return $this->locations->getInventoryLocationById($id) ?? throw new InvalidArgumentException('Resource not found.');
    }

    private function item(int $id): void
    {
        if (!$this->inventory->getInventoryItemQuery()->where(['id' => $id])->exists()) {
            throw new InvalidArgumentException('Resource not found.');
        }
    }
}
