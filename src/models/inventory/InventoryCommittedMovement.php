<?php

namespace craft\commerce\models\inventory;

use craft\commerce\base\InventoryMovement;
use craft\commerce\enums\InventoryTransactionType;

/**
 * Inventory committed movement model
 *
 * @since 5.0
 */
class InventoryCommittedMovement extends InventoryMovement
{
    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['fromInventoryTransactionType', 'toInventoryTransactionType'],
            function($attribute, $params, $validator) {
                if ($this->fromInventoryTransactionType !== InventoryTransactionType::AVAILABLE && $this->toInventoryTransactionType !== InventoryTransactionType::COMMITTED) {
                    $validator->addError($this, $attribute, 'Invalid committed transaction types');
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

        return $rules;
    }
}
