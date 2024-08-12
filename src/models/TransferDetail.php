<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Transfer;
use craft\commerce\enums\TransferStatusType;

class TransferDetail extends Model
{
    public ?int $id = null;

    public ?int $transferId = null;

    public ?int $inventoryItemId;

    public string $inventoryItemDescription = '';

    public int $quantity = 0;

    public int $quantityAccepted = 0;

    public int $quantityRejected = 0;

    private ?Transfer $_transfer;

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        parent::init();

        if ($this->transferId) {
            $this->_transfer = Transfer::findOne($this->transferId);
        }
    }

    /**
     * @return Transfer
     */
    public function getTransfer(): Transfer
    {
        return $this->_transfer;
    }

    public function defineRules(): array
    {
        return [
            [['quantity'], 'number', 'integerOnly' => true, 'min' => 1, 'when' => function() {
                return $this->getTransfer()->transferStatus === TransferStatusType::DRAFT;
            }],
        ];
    }
}
