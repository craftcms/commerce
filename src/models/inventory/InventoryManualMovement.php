<?php

namespace craft\commerce\models\inventory;

use craft\commerce\base\InventoryMovement;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryTransactionType;
use craft\db\Query;

/**
 * Inventory Manual movement model
 */
class InventoryManualMovement extends InventoryMovement
{
    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['fromInventoryTransactionType'],
            function($attribute, $params, $validator) {
                if (!$this->{$attribute}->canBeNegative() && $this->fromLocationAfterQuantity() < 0) {
                    $validator->addError($this, $attribute, 'The {inventoryLocation} inventory location’s {type} stock would drop below zero.',
                        [
                            'inventoryLocation' => $this->fromInventoryLocation->getUiLabel(),
                            'type' => $this->{$attribute}->typeAsLabel(),
                        ]
                    );
                }
            },
        ];

        $rules[] = [
            ['toInventoryTransactionType'],
            function($attribute, $params, $validator) {
                if (!$this->{$attribute}->canBeNegative() && $this->toLocationAfterQuantity() < 0) {
                    $validator->addError($this, $attribute, 'The {inventoryLocation} inventory location stock of {type} would drop below zero.',
                        [
                            'inventoryLocation' => $this->toInventoryLocation->getUiLabel(),
                            'type' => $this->{$attribute}->typeAsLabel(),
                        ]
                    );
                }
            },
        ];

        $rules[] = [
            ['fromInventoryLocation', 'toInventoryLocation'],
            function($attribute, $params, $validator) {
                if ($this->fromInventoryLocation->id !== $this->toInventoryLocation->id) {
                    $validator->addError($this, $attribute, 'The from and to inventory locations must be the same.');
                }
            },
        ];

        $rules[] = [
            ['toInventoryTransactionType'],
            function($attribute, $params, $validator) {
                if ($this->isManualMovement() &&
                    (
                        !in_array($this->fromInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes()) ||
                        !in_array($this->toInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes())
                    )
                ) {
                    $validator->addError($this, $attribute, \Craft::t('commerce','Can not move between these inventory types.'));
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
            ->from(Table::INVENTORYTRANSACTIONS)
            ->where([
                'type' => $this->fromInventoryTransactionType->value,
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
            $this->lineItemId === null && $this->transferId === null
        );
    }

    /**
     * @return int
     */
    public function toLocationAfterQuantity(): int
    {
        return (new Query())
            ->select(['quantity' => new \yii\db\Expression('COALESCE(SUM(quantity), 0) + :quantity')])
            ->from(Table::INVENTORYTRANSACTIONS)
            ->where([
                'type' => $this->toInventoryTransactionType->value,
                'inventoryItemId' => $this->inventoryItem->id,
                'inventoryLocationId' => $this->toInventoryLocation->id,
            ])
            ->params([':quantity' => $this->quantity])
            ->scalar();
    }
}
