<?php

namespace craft\commerce\models\inventory;

use Craft;
use craft\commerce\base\InventoryMovement;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\Plugin;

/**
 * Inventory Fulfill movement model
 *
 * @since 5.0
 */
class InventoryFulfillMovement extends InventoryMovement
{
    /**
     * @var int The quantity of the same inventory item being fulfilled from the same location alongside this movement
     * (e.g. another line item for the same purchasable in the same request), which isn't in the ledger yet.
     * @since 5.7.7
     */
    public int $otherPendingQuantity = 0;

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

        $rules[] = [
            ['quantity'],
            function($attribute, $params, $validator) {
                if ($this->quantity <= 0) {
                    return;
                }

                $fulfillable = Plugin::getInstance()->getInventory()->getFulfillableQuantity($this->inventoryItemId, $this->fromInventoryLocation->id);

                if ($this->quantity + $this->otherPendingQuantity > $fulfillable) {
                    $validator->addError($this, $attribute, Craft::t('commerce',
                        'Only {quantity} can be fulfilled from {inventoryLocation}. Transfer more stock to this location before fulfilling the rest.',
                        [
                            'quantity' => max($fulfillable - $this->otherPendingQuantity, 0),
                            'inventoryLocation' => $this->fromInventoryLocation->getUiLabel(),
                        ]
                    ));
                }
            },
        ];

        return $rules;
    }
}
