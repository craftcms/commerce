<?php

namespace craft\commerce\models\inventory;

use craft\commerce\base\InventoryMovement;

/**
 * Inventory Transfer movement model
 */
class InventoryTransferMovement extends InventoryMovement
{
    /**
     * @return array
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();
        return $rules;
    }
}
