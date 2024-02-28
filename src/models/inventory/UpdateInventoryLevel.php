<?php

namespace craft\commerce\models\inventory;

use craft\base\Model;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\InventoryItem;
use craft\commerce\models\InventoryLocation;

/**
 * Update (Set and Adjust) Inventory Quantity model
 *
 * @since 5.0
 */
class UpdateInventoryLevel extends Model
{
    /**
     * The type is the set of InventoryTransactionType values, plus the `onHand` type.
     * @var string The inventory update type.
     */
    public string $type;

    /**
     * @var InventoryUpdateQuantityType The action to perform on the inventory.
     */
    public InventoryUpdateQuantityType $updateAction;

    /**
     * @var InventoryItem The inventory item
     */
    public InventoryItem $inventoryItem;

    /**
     * @var InventoryLocation The inventory location.
     */
    public InventoryLocation $inventoryLocation;

    /**
     * @var int The quantity to update.
     */
    public int $quantity;

    /**
     * @var string A note about the inventory update.
     */
    public string $note = '';

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['updateAction', 'quantity', 'inventoryLocationId', 'inventoryId', 'type'], 'required'],
            [['note'], 'string'],
            [['type'], 'in', 'range' => [...InventoryTransactionType::allowedManualAdjustmentTypes(), 'onHand']],
            [['updateAction'], 'in', 'range' => InventoryUpdateQuantityType::values()],
        ]);
    }
}
