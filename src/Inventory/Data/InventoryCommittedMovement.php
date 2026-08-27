<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Data;

use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;

class InventoryCommittedMovement extends InventoryMovement
{
    #[\Override]
    public function getRules(): array
    {
        return [
            'fromInventoryTransactionType' => [
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($this->fromInventoryTransactionType !== InventoryTransactionType::AVAILABLE || $this->toInventoryTransactionType !== InventoryTransactionType::COMMITTED) {
                        $fail('Invalid committed transaction types');
                    }

                    if ($this->fromInventoryLocation->id !== $this->toInventoryLocation->id) {
                        $fail('The from and to inventory locations must be the same.');
                    }
                },
            ],
        ];
    }
}
