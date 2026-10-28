<?php

namespace craft\commerce\models\inventory;

use craft\commerce\enums\InventoryTransactionType;

/**
 * Update (Set and Adjust) Inventory Quantity model
 *
 * @since 5.0
 */
class UpdateInventoryLevelInTransfer extends UpdateInventoryLevel
{
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        // Update the `['type']` rule to only allow incoming, available, reserved (for order-linked transfer
        // debits) and onHand
        foreach ($rules as &$item) {
            if ($item[0] !== ['type']) {
                continue;
            }

            $item['range'] = [
                ...InventoryTransactionType::incoming(),
                InventoryTransactionType::AVAILABLE->value,
                InventoryTransactionType::RESERVED->value,
                'onHand',
            ];
        }

        return $rules;
    }
}
