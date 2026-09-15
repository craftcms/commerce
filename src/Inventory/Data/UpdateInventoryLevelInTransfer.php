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
        $rules['type'] = ['required', Rule::in([...$incomingTypes, 'onHand'])];

        return $rules;
    }
}
