<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use function CraftCms\Cms\t;

class InventoryLocationDeactivatedMovement extends InventoryMovement
{
    #[\Override]
    public function getRules(): array
    {
        return [
            'fromInventoryLocation' => [
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($this->fromInventoryLocation->id === $this->toInventoryLocation->id) {
                        $fail(t('The from and to inventory locations must be different.', category: 'commerce'));
                    }

                    if (!in_array($this->fromInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes(), true)) {
                        $fail('Can not move between these inventory types.');
                    }

                    if (!in_array($this->toInventoryTransactionType, InventoryTransactionType::allowedManualMoveTransactionTypes(), true)) {
                        $fail('Can not move between these inventory types.');
                    }
                },
            ],
        ];
    }
}
