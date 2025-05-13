<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Transfer;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\Plugin;

class TransferDetail extends Model
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

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        if ($this->transferId) {
            $this->_transfer = Transfer::findOne($this->transferId);
        }

        if ($this->inventoryItemId) {
            $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);
            $this->inventoryItemDescription = $inventoryItem->getSku();
        }
    }

    public function getReceived(): int
    {
        return $this->quantityAccepted + $this->quantityRejected;
    }

    /**
     * @return ?InventoryItem
     */
    public function getInventoryItem(): ?InventoryItem
    {
        if ($this->inventoryItemId === null) {
            return null;
        }

        return Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);
    }

    /**
     * @return Transfer
     */
    public function getTransfer(): Transfer
    {
        return $this->_transfer;
    }

    /**
     * @return void
     */
    public function setTransfer(Transfer $transfer): void
    {
        $this->transferId = $transfer->id;
        $this->_transfer = $transfer;
    }

    public function defineRules(): array
    {
        return [
            [['quantity'], 'number', 'integerOnly' => true, 'min' => 1, 'max' => 99999, 'when' => function() {
                return $this->getTransfer()->transferStatus === TransferStatusType::DRAFT;
            }],
        ];
    }
}
