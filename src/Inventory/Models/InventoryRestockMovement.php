<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;

class InventoryRestockMovement extends InventoryMovement
{
    #[\Override]
    public function getRules(): array
    {
        return [
            'fromInventoryTransactionType' => [
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($this->fromInventoryTransactionType !== InventoryTransactionType::COMMITTED || $this->toInventoryTransactionType !== InventoryTransactionType::AVAILABLE) {
                        $fail('Invalid Restock transaction type');
                    }

                    if ($this->fromInventoryLocation->id !== $this->toInventoryLocation->id) {
                        $fail('The from and to inventory locations must be the same.');
                    }
                },
            ],
        ];
    }
}
