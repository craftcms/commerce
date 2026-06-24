<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Models;

use craft\commerce\elements\Transfer;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Inventory\Models\InventoryItem;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;

class TransferDetail extends Component
{
    public ?int $id = null;

    public ?int $transferId = null;

    public ?int $inventoryItemId = null;

    public string $inventoryItemDescription = '';

    public int $quantity = 0;

    public int $quantityAccepted = 0;

    public int $quantityRejected = 0;

    public string $uid;

    private ?Transfer $_transfer = null;

    public function getReceived(): int
    {
        return $this->quantityAccepted + $this->quantityRejected;
    }

    public function getInventoryItem(): ?InventoryItem
    {
        if ($this->inventoryItemId === null) {
            return null;
        }

        // TODO: migrate to app(Inventory::class)->getInventoryItemById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);
    }

    public function getTransfer(): ?Transfer
    {
        if ($this->_transfer !== null) {
            return $this->_transfer;
        }

        if ($this->transferId) {
            /** @phpstan-ignore-next-line */
            $this->_transfer = Transfer::find()->id($this->transferId)->one();
        }

        return $this->_transfer;
    }

    public function setTransfer(Transfer $transfer): void
    {
        /** @phpstan-ignore-next-line */
        $this->transferId = $transfer->id;
        $this->_transfer = $transfer;
    }

    #[\Override]
    public function getRules(): array
    {
        $transfer = $this->_transfer;

        if ($transfer && $transfer->transferStatus === TransferStatusType::DRAFT) {
            return ['quantity' => ['integer', 'min:1', 'max:99999']];
        }

        return [];
    }
}
