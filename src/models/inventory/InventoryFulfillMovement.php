<?php

namespace craft\commerce\models\inventory;

use craft\commerce\base\InventoryMovement;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryTransactionType;
use craft\db\Query;

/**
 * Inventory Fulfill movement model
 */
class InventoryFulfillMovement extends InventoryMovement
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
                if ($this->fromInventoryTransactionType !== InventoryTransactionType::COMMITTED && $this->toInventoryTransactionType !== InventoryTransactionType::FULFILLED) {
                    $validator->addError($this, $attribute, 'Invalid Restock transaction type');
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
