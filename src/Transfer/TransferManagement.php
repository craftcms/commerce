<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer;

use CraftCms\Cms\Element\UserInitiatedElementSave;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Management\Access;
use CraftCms\Commerce\Management\Input;
use CraftCms\Commerce\Transfer\Data\TransferDetail;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

readonly class TransferManagement
{
    public function __construct(private Access $access, private Transfers $transfers)
    {
    }

    public function details(CraftUser $actor, int $id): array
    {
        $transfer = $this->find($actor, $id, 'view');
        return ['count' => count($transfer->getDetails()), 'items' => array_map(static fn(TransferDetail $detail) => ['uid' => $detail->uid, 'inventoryItemId' => $detail->inventoryItemId, 'quantity' => $detail->quantity, 'quantityAccepted' => $detail->quantityAccepted, 'quantityRejected' => $detail->quantityRejected], $transfer->getDetails())];
    }

    public function save(CraftUser $actor, int $originLocationId, int $destinationLocationId, array $items, ?int $id = null): Transfer
    {
        $this->access->permission($actor, 'commerce-manageInventoryTransfers');
        Input::validate(['items' => $items], ['type' => 'object', 'properties' => ['items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => ['type' => 'object', 'properties' => ['inventoryItemId' => ['type' => 'integer', 'minimum' => 1], 'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 99999]], 'required' => ['inventoryItemId', 'quantity'], 'additionalProperties' => false]]]]);
        return Cache::lock('commerce:transfer-management:' . ($id ?? 'new'), 30)->block(5, function() use ($actor, $originLocationId, $destinationLocationId, $items, $id): Transfer {
            $transfer = $id === null ? new Transfer() : $this->find($actor, $id, 'save');
            if (!$transfer->isTransferDraft()) {
                throw new InvalidArgumentException('Only draft transfers can be edited.');
            }
            $locations = app(InventoryLocations::class);
            if ($originLocationId === $destinationLocationId || !$locations->getInventoryLocationById($originLocationId) || !$locations->getInventoryLocationById($destinationLocationId)) {
                throw new InvalidArgumentException('Choose two distinct active inventory locations.');
            }
            $transfer->originLocationId = $originLocationId;
            $transfer->destinationLocationId = $destinationLocationId;
            $details = [];
            foreach ($items as $item) {
                if (isset($details[$item['inventoryItemId']]) || !app(Inventory::class)->getInventoryItemQuery()->where('id', $item['inventoryItemId'])->exists()) {
                    throw new InvalidArgumentException('Inventory items must exist and must not be repeated.');
                }
                $details[$item['inventoryItemId']] = new TransferDetail($item);
            }
            $transfer->setDetails(array_values($details));
            Gate::forUser($actor)->authorize('save', $transfer);
            $result = app(UserInitiatedElementSave::class)->save($transfer, $actor);
            if (!$result->successful) {
                throw ValidationException::withMessages($transfer->getErrors());
            }
            return $transfer;
        });
    }

    public function dispatch(CraftUser $actor, int $id): Transfer
    {
        $this->find($actor, $id, 'save');
        return Cache::lock("commerce:transfer-management:$id", 30)->block(5, function() use ($actor, $id): Transfer {
            $transfer = $this->find($actor, $id, 'save');
            if (!$this->transfers->markAsPending($transfer)) {
                throw new InvalidArgumentException('Only a valid draft transfer can be dispatched.');
            }
            return $transfer;
        });
    }

    public function receive(CraftUser $actor, int $id, array $quantities): Transfer
    {
        $this->find($actor, $id, 'save');
        Input::validate($quantities, ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 100, 'additionalProperties' => ['type' => 'object', 'properties' => ['accept' => ['type' => 'integer', 'minimum' => 0], 'reject' => ['type' => 'integer', 'minimum' => 0]], 'minProperties' => 1, 'additionalProperties' => false]]);
        return Cache::lock("commerce:transfer-management:$id", 30)->block(5, function() use ($actor, $id, $quantities): Transfer {
            $transfer = $this->find($actor, $id, 'save');
            if (!in_array($transfer->transferStatus, [TransferStatusType::PENDING, TransferStatusType::PARTIAL], true)) {
                throw new InvalidArgumentException('Only pending or partially received transfers can be received.');
            }
            $details = collect($transfer->getDetails())->keyBy('uid');
            foreach ($quantities as $uid => $quantity) {
                $detail = $details->get($uid);
                $received = ($quantity['accept'] ?? 0) + ($quantity['reject'] ?? 0);
                if (!$detail || $received < 1 || $received > $detail->quantity - $detail->getReceived()) {
                    throw new InvalidArgumentException('Receipt must reference this transfer and cannot exceed its outstanding quantity.');
                }
            }
            $this->transfers->receive($transfer, $quantities);
            return $transfer;
        });
    }

    private function find(CraftUser $actor, int $id, string $ability): Transfer
    {
        $this->access->permission($actor, 'commerce-manageInventoryTransfers');
        $transfer = Transfer::find()->id($id)->status(null)->one();
        if (!$transfer || !Gate::forUser($actor)->allows($ability, $transfer)) {
            throw new InvalidArgumentException('Resource not found.');
        }
        return $transfer;
    }
}
