<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\models\InventoryLocation;

/**
 * Interface for inventory movements that will create movement transactions when executed.
 *
 * @since 5.0.0
 */
abstract class InventoryMovement extends Model implements InventoryMovementInterface
{
    use InventoryItemTrait;

    /**
     * @var InventoryLocation
     */
    public InventoryLocation $fromInventoryLocation;

    /**
     * @var InventoryLocation
     */
    public InventoryLocation $toInventoryLocation;

    /**
     * @var InventoryTransactionType
     */
    public InventoryTransactionType $fromInventoryTransactionType;

    /**
     * @var InventoryTransactionType
     */
    public InventoryTransactionType $toInventoryTransactionType;

    /**
     * @var int
     */
    public int $quantity;

    /**
     * The Transfer that made this movement
     *
     * @var ?int
     */
    public ?int $transferId = null;

    /**
     * The line item that made this movement
     *
     * @var ?int
     */
    public ?int $lineItemId = null;

    /**
     * Who made this movement (if known).
     *
     * @var ?int
     */
    public ?int $userId = null;

    /**
     * @var string
     */
    public string $note = '';

    /**
     * @var ?string
     */
    private ?string $_inventoryMovementHash = null;

    /**
     * @return void
     */
    public function init(): void
    {
        $this->_inventoryMovementHash = md5(uniqid((string)mt_rand(), true));

        parent::init();
    }

    /**
     * @return array
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['inventoryItemId'], 'safe'];

        return $rules;
    }

    /**
     * @inheritDoc
     */
    public function isValid(): bool
    {
        return $this->validate();
    }

    /**
     * @inheritDoc
     */
    public function getInventoryMovementHash(): string
    {
        return $this->_inventoryMovementHash;
    }

    /**
     * @inheritDoc
     */
    public function getToInventoryLocation(): InventoryLocation
    {
        return $this->toInventoryLocation;
    }

    /**
     * @inheritDoc
     */
    public function getFromInventoryLocation(): InventoryLocation
    {
        return $this->fromInventoryLocation;
    }

    /**
     * @inheritDoc
     */
    public function getToInventoryTransactionType(): InventoryTransactionType
    {
        return $this->toInventoryTransactionType;
    }

    /**
     * @inheritDoc
     */
    public function getFromInventoryTransactionType(): InventoryTransactionType
    {
        return $this->fromInventoryTransactionType;
    }

    /**
     * @inheritDoc
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @inheritDoc
     */
    public function getTransferId(): ?int
    {
        return $this->transferId;
    }

    /**
     * @inheritDoc
     */
    public function getLineItemId(): ?int
    {
        return $this->lineItemId;
    }

    /**
     * @inheritDoc
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @inheritDoc
     */
    public function getNote(): ?string
    {
        return $this->note;
    }
}
