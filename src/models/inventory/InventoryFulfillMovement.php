<?php

namespace craft\commerce\models\inventory;

use Craft;
use craft\commerce\base\InventoryMovement;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryTransactionType;
use craft\db\Query;
use yii\db\Expression;

/**
 * Inventory Fulfill movement model
 *
 * @since 5.0
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

        $rules[] = [
            ['fromInventoryLocation'],
            function($attribute, $params, $validator) {
                if ($this->fromLocationOnHandAfterQuantity() < 0) {
                    $validator->addError($this, $attribute, Craft::t('commerce',
                        'The {inventoryLocation} inventory location does not have enough physical stock on hand to fulfill this quantity.',
                        ['inventoryLocation' => $this->fromInventoryLocation->getUiLabel()]
                    ));
                }
            },
        ];

        return $rules;
    }

    /**
     * Returns what the on-hand total (everything physically present: unavailable + available + committed)
     * at the `fromInventoryLocation` would be after this fulfillment is applied.
     *
     * @return int
     */
    public function fromLocationOnHandAfterQuantity(): int
    {
        return (int)(new Query())
            ->select(['quantity' => new Expression('COALESCE(SUM(quantity), 0) - :quantity')])
            ->from(Table::INVENTORYTRANSACTIONS)
            ->where([
                'type' => collect(InventoryTransactionType::onHand())->pluck('value')->all(),
                'inventoryItemId' => $this->inventoryItemId,
                'inventoryLocationId' => $this->fromInventoryLocation->id,
            ])
            ->params([':quantity' => $this->quantity])
            ->scalar();
    }
}
