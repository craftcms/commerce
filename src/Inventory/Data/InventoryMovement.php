<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Inventory\Concerns\InventoryItemTrait;
use CraftCms\Commerce\Inventory\Contracts\InventoryMovementInterface;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;

abstract class InventoryMovement extends Component implements InventoryMovementInterface
{
    use InventoryItemTrait;

    public InventoryLocation $fromInventoryLocation;

    public InventoryLocation $toInventoryLocation;

    public InventoryTransactionType $fromInventoryTransactionType;

    public InventoryTransactionType $toInventoryTransactionType;

    public int $quantity;

    public ?int $transferId = null;

    public ?int $lineItemId = null;

    public ?int $userId = null;

    public string $note = '';

    private ?string $_inventoryMovementHash = null;

    #[\Override]
    public function isValid(): bool
    {
        return $this->validate();
    }

    public function getInventoryMovementHash(): string
    {
        if ($this->_inventoryMovementHash === null) {
            $this->_inventoryMovementHash = md5(uniqid((string)mt_rand(), true));
        }

        return $this->_inventoryMovementHash;
    }

    #[\Override]
    public function getToInventoryLocation(): InventoryLocation
    {
        return $this->toInventoryLocation;
    }

    #[\Override]
    public function getFromInventoryLocation(): InventoryLocation
    {
        return $this->fromInventoryLocation;
    }

    #[\Override]
    public function getToInventoryTransactionType(): InventoryTransactionType
    {
        return $this->toInventoryTransactionType;
    }

    #[\Override]
    public function getFromInventoryTransactionType(): InventoryTransactionType
    {
        return $this->fromInventoryTransactionType;
    }

    #[\Override]
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    #[\Override]
    public function getTransferId(): ?int
    {
        return $this->transferId;
    }

    #[\Override]
    public function getLineItemId(): ?int
    {
        return $this->lineItemId;
    }

    #[\Override]
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    #[\Override]
    public function getNote(): ?string
    {
        return $this->note;
    }
}
