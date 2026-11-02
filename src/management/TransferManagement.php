<?php

declare(strict_types=1);

namespace craft\commerce\management;

use Craft;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\elements\Transfer;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\models\inventory\InventoryTransferMovement;
use craft\commerce\models\inventory\UpdateInventoryLevelInTransfer;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\elements\User;
use InvalidArgumentException;

readonly class TransferManagement
{
    public function __construct(private Access $access)
    {
    }

    public function details(User $actor, int $id): array
    {
        $transfer = $this->find($actor, $id, 'view');
        return ['count' => count($transfer->getDetails()), 'items' => array_map(static fn(TransferDetail $detail) => ['uid' => $detail->uid, 'inventoryItemId' => $detail->inventoryItemId, 'quantity' => $detail->quantity, 'quantityAccepted' => $detail->quantityAccepted, 'quantityRejected' => $detail->quantityRejected], $transfer->getDetails())];
    }

    public function save(User $actor, int $originLocationId, int $destinationLocationId, array $items, ?int $id = null): Transfer
    {
        $this->access->permission($actor, 'commerce-manageInventoryTransfers');
        Input::validate(['items' => $items], ['type' => 'object', 'properties' => ['items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => ['type' => 'object', 'properties' => ['inventoryItemId' => ['type' => 'integer', 'minimum' => 1], 'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 99999]], 'required' => ['inventoryItemId', 'quantity'], 'additionalProperties' => false]]]]);
        return Mutation::run('commerce:transfer-management:' . ($id ?? 'new'), function() use ($actor, $originLocationId, $destinationLocationId, $items, $id): Transfer {
            $transfer = $id === null ? new Transfer() : $this->find($actor, $id, 'save');
            if (!$transfer->isTransferDraft()) {
                throw new InvalidArgumentException('Only draft transfers can be edited.');
            }
            $locations = Plugin::getInstance()->getInventoryLocations();
            if ($originLocationId === $destinationLocationId || !$locations->getInventoryLocationById($originLocationId) || !$locations->getInventoryLocationById($destinationLocationId)) {
                throw new InvalidArgumentException('Choose two distinct active inventory locations.');
            }
            $transfer->originLocationId = $originLocationId;
            $transfer->destinationLocationId = $destinationLocationId;
            $details = [];
            foreach ($items as $item) {
                if (isset($details[$item['inventoryItemId']]) || !Plugin::getInstance()->getInventory()->getInventoryItemQuery()->where(['id' => $item['inventoryItemId']])->exists()) {
                    throw new InvalidArgumentException('Inventory items must exist and must not be repeated.');
                }
                $details[$item['inventoryItemId']] = new TransferDetail($item);
            }
            $transfer->setDetails(array_values($details));
            if (!Access::allows($actor, 'save', $transfer) || !Craft::$app->getElements()->saveElement($transfer)) {
                throw new InvalidArgumentException(implode("\n", $transfer->getErrorSummary(true)) ?: 'Resource not found.');
            }
            return $transfer;
        });
    }

    public function dispatch(User $actor, int $id): Transfer
    {
        $this->find($actor, $id, 'save');
        return Mutation::run("commerce:transfer-management:$id", function() use ($actor, $id): Transfer {
            $transfer = $this->find($actor, $id, 'save');
            if (!$transfer->isTransferDraft() || !$transfer->validate()) {
                throw new InvalidArgumentException('Only a valid draft transfer can be dispatched.');
            }
            // Validate details while still a persisted draft, before the pending transition.
            foreach ($transfer->getDetails() as $detail) {
                if (!$detail->validate()) {
                    throw new InvalidArgumentException(implode("\n", $detail->getErrorSummary(true)));
                }
            }
            $transfer->transferStatus = TransferStatusType::PENDING;
            if (!Craft::$app->getElements()->saveElement($transfer)) {
                throw new InvalidArgumentException(implode("\n", $transfer->getErrorSummary(true)));
            }
            return $transfer;
        });
    }

    public function receive(User $actor, int $id, array $quantities): Transfer
    {
        $this->find($actor, $id, 'save');
        Input::validate($quantities, ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 100, 'additionalProperties' => ['type' => 'object', 'properties' => ['accept' => ['type' => 'integer', 'minimum' => 0], 'reject' => ['type' => 'integer', 'minimum' => 0]], 'minProperties' => 1, 'additionalProperties' => false]]);
        return Mutation::run("commerce:transfer-management:$id", function() use ($actor, $id, $quantities): Transfer {
            $transfer = $this->find($actor, $id, 'save');
            if (!in_array($transfer->transferStatus, [TransferStatusType::PENDING, TransferStatusType::PARTIAL], true)) {
                throw new InvalidArgumentException('Only pending or partially received transfers can be received.');
            }
            $details = collect($transfer->getDetails())->keyBy('uid');
            /** @var array<string, array{accept?: int, reject?: int}> $quantities */
            foreach ($quantities as $uid => $quantity) {
                $detail = $details->get($uid);
                $received = ($quantity['accept'] ?? 0) + ($quantity['reject'] ?? 0);
                if (!$detail || $received < 1 || $received > $detail->quantity - $detail->getReceived()) {
                    throw new InvalidArgumentException('Receipt must reference this transfer and cannot exceed its outstanding quantity.');
                }
            }
            Craft::$app->getDb()->transaction(function() use ($transfer, $details, $quantities): void {
                $movements = new InventoryMovementCollection();
                $updates = new UpdateInventoryLevelCollection();
                foreach ($quantities as $uid => $quantity) {
                    $detail = $details->get($uid);
                    if ($accepted = $quantity['accept'] ?? 0) {
                        $movements->push(new InventoryTransferMovement([
                            'inventoryItemId' => $detail->inventoryItemId,
                            'transferId' => $transfer->id,
                            'quantity' => $accepted,
                            'fromInventoryLocation' => $transfer->getDestinationLocation(),
                            'toInventoryLocation' => $transfer->getDestinationLocation(),
                            'fromInventoryTransactionType' => InventoryTransactionType::INCOMING,
                            'toInventoryTransactionType' => InventoryTransactionType::AVAILABLE,
                        ]));
                        $detail->quantityAccepted += $accepted;
                    }
                    if ($rejected = $quantity['reject'] ?? 0) {
                        $updates->push(new UpdateInventoryLevelInTransfer([
                            'inventoryItemId' => $detail->inventoryItemId,
                            'inventoryLocationId' => $transfer->destinationLocationId,
                            'transferId' => $transfer->id,
                            'quantity' => -$rejected,
                            'type' => InventoryTransactionType::INCOMING->value,
                            'updateAction' => InventoryUpdateQuantityType::ADJUST,
                        ]));
                        $detail->quantityRejected += $rejected;
                    }
                }
                $inventory = Plugin::getInstance()->getInventory();
                if (!$inventory->executeInventoryMovements($movements) || !$inventory->executeUpdateInventoryLevels($updates)
                    || !Craft::$app->getElements()->saveElement($transfer)) {
                    throw new InvalidArgumentException('The transfer could not be received.');
                }
            });
            return $transfer;
        });
    }

    private function find(User $actor, int $id, string $ability): Transfer
    {
        $this->access->permission($actor, 'commerce-manageInventoryTransfers');
        $transfer = Transfer::find()->id($id)->status(null)->one();
        if (!$transfer instanceof Transfer || !Access::allows($actor, $ability, $transfer)) {
            throw new InvalidArgumentException('Resource not found.');
        }
        return $transfer;
    }
}
