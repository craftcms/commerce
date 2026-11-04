<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Data;

use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Inventory;
use function CraftCms\Cms\t;

class InventoryFulfillMovement extends InventoryMovement
{
    /**
     * The quantity of the same inventory item being fulfilled from the same location alongside this movement
     * (e.g. another line item for the same purchasable in the same request), which isn't in the ledger yet.
     */
    public int $otherPendingQuantity = 0;

    #[\Override]
    public function getRules(): array
    {
        return [
            'fromInventoryTransactionType' => [
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($this->fromInventoryTransactionType !== InventoryTransactionType::COMMITTED || $this->toInventoryTransactionType !== InventoryTransactionType::FULFILLED) {
                        $fail('Invalid Restock transaction type');
                    }

                    if ($this->fromInventoryLocation->id !== $this->toInventoryLocation->id) {
                        $fail('The from and to inventory locations must be the same.');
                    }
                },
            ],
            'quantity' => [
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($this->quantity <= 0) {
                        return;
                    }

                    $fulfillable = app(Inventory::class)->getFulfillableQuantity($this->inventoryItemId, $this->fromInventoryLocation->id);

                    if ($this->quantity + $this->otherPendingQuantity > $fulfillable) {
                        $fail(t('{description} only has {stock} in stock.', [
                            'description' => $this->fromInventoryLocation->getUiLabel(),
                            'stock' => max($fulfillable - $this->otherPendingQuantity, 0),
                        ], category: 'commerce'));
                    }
                },
            ],
        ];
    }
}
