<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Data;

use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use Illuminate\Validation\Rule;

class UpdateInventoryLevelInTransfer extends UpdateInventoryLevel
{
    #[\Override]
    public function getRules(): array
    {
        $rules = parent::getRules();

        $incomingTypes = array_map(fn($t) => $t->value, InventoryTransactionType::incoming());
        // Only allow incoming, available, reserved (for order-linked transfer debits) and onHand
        $rules['type'] = ['required', Rule::in([
            ...$incomingTypes,
            InventoryTransactionType::AVAILABLE->value,
            InventoryTransactionType::RESERVED->value,
            'onHand',
        ])];

        return $rules;
    }
}
