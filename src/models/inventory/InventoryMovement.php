<?php

namespace craft\commerce\models\inventory;

use craft\commerce\base\Model;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryMovementType;
use craft\commerce\models\InventoryItem;
use craft\commerce\models\InventoryLocation;
use craft\db\Query;

/**
 * Inventory Item model
 */
class InventoryMovement extends Model
{
    /**
     * @var InventoryItem The inventory item
     */
    public InventoryItem $inventoryItem;

    /**
     * @var InventoryLocation
     */
    public InventoryLocation $fromInventoryLocation;

    /**
     * @var InventoryLocation
     */
    public InventoryLocation $toInventoryLocation;

    /**
     * @var InventoryMovementType
     */
    public InventoryMovementType $fromInventoryMovementType;

    /**
     * @var InventoryMovementType
     */
    public InventoryMovementType $toInventoryMovementType;

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
     * The Order that made this movement
     *
     * @var ?int
     */
    public ?int $orderId = null;

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
     * @return string
     */
    public function getInventoryMovementHash(): string
    {
        return $this->_inventoryMovementHash;
    }

    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['fromInventoryMovementType'],
            function($attribute, $params, $validator) {
                if (!$this->{$attribute}->canBeNegative() && $this->fromLocationAfterQuantity() < 0) {
                    $validator->addError($this, $attribute, 'The {inventoryLocation} inventory location’s {type} stock would drop below zero.',
                        [
                            'inventoryLocation' => $this->fromInventoryLocation->name,
                            'type' => $this->{$attribute}->typeAsLabel(),
                        ]
                    );
                }
            },
        ];

        $rules[] = [
            ['toInventoryMovementType'],
            function($attribute, $params, $validator) {
                if (!$this->{$attribute}->canBeNegative() && $this->toLocationAfterQuantity() < 0) {
                    $validator->addError($this, $attribute, 'The {inventoryLocation} inventory location stock of {type} would drop below zero.',
                        [
                            'inventoryLocation' => $this->toInventoryLocation->name,
                            'type' => $this->{$attribute}->typeAsLabel(),
                        ]
                    );
                }
            },
        ];

        $rules[] = [
            ['fromInventoryLocation', 'toInventoryLocation'],
            function($attribute, $params, $validator) {
                if (!$this->transferId && $this->fromInventoryLocation->id !== $this->toInventoryLocation->id) {
                    $validator->addError($this, $attribute, 'The from and to inventory locations must be the same.');
                }

                if ($this->transferId && $this->fromInventoryLocation->id === $this->toInventoryLocation->id) {
                    $validator->addError($this, $attribute, 'The from and to inventory locations must be different.');
                }
            },
        ];

        $rules[] = [
            ['toInventoryMovementType'],
            function($attribute, $params, $validator) {
                if ($this->isManualMovement() &&
                    (
                        !in_array($this->fromInventoryMovementType, InventoryMovementType::allowedManualMovementTypes()) ||
                        !in_array($this->toInventoryMovementType, InventoryMovementType::allowedManualMovementTypes())
                    )
                ) {
                    $validator->addError($this, $attribute, 'Can not manually move between these inventory types.');
                }

                if (!$this->isManualMovement()) {
                    if ($this->toInventoryMovementType === InventoryMovementType::COMMITTED) {
                        if ($this->fromInventoryMovementType !== InventoryMovementType::AVAILABLE) {
                            $validator->addError($this, $attribute, 'Can not move to committed from this movement type.');
                        }
                    }
                }

                if (!$this->isManualMovement()) {
                    if ($this->toInventoryMovementType === InventoryMovementType::INCOMING) {
                        if ($this->fromInventoryMovementType !== InventoryMovementType::AVAILABLE) {
                            $validator->addError($this, $attribute, 'Can not move to incoming from this movement type.');
                        }
                    }
                }
            },
        ];

        return $rules;
    }

    /**
     * @return int
     */
    public function fromLocationAfterQuantity(): int
    {
        return (new Query())
            ->select(['quantity' => new \yii\db\Expression('COALESCE(SUM(quantity), 0) - :quantity')])
            ->from(Table::INVENTORYMOVEMENTS)
            ->where([
                'type' => $this->fromInventoryMovementType->value,
                'inventoryItemId' => $this->inventoryItem->id,
                'inventoryLocationId' => $this->fromInventoryLocation->id,
            ])
            ->params([':quantity' => $this->quantity])
            ->scalar();
    }

    /**
     * Determines if this is a manual movement between available and unavailable inventory.
     *
     * @return bool
     */
    public function isManualMovement(): bool
    {
        return (
            $this->lineItemId === null && $this->orderId === null && $this->transferId === null
        );
    }

    /**
     * @return int
     */
    public function toLocationAfterQuantity(): int
    {
        return (new Query())
            ->select(['quantity' => new \yii\db\Expression('COALESCE(SUM(quantity), 0) + :quantity')])
            ->from(Table::INVENTORYMOVEMENTS)
            ->where([
                'type' => $this->toInventoryMovementType->value,
                'inventoryItemId' => $this->inventoryItem->id,
                'inventoryLocationId' => $this->toInventoryLocation->id,
            ])
            ->params([':quantity' => $this->quantity])
            ->scalar();
    }
}
