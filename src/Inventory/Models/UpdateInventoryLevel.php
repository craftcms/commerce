<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Inventory\Concerns\InventoryItemTrait;
use CraftCms\Commerce\Inventory\Concerns\InventoryLocationTrait;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use Illuminate\Validation\Rule;

class UpdateInventoryLevel extends Component
{
    use InventoryItemTrait, InventoryLocationTrait;

    public string $type;

    public ?int $transferId = null;

    public InventoryUpdateQuantityType $updateAction;

    public int $quantity;

    public string $note = '';

    #[\Override]
    public function getRules(): array
    {
        $allowedTypes = array_map(fn($t) => $t->value, InventoryTransactionType::allowedManualAdjustmentTypes());

        return [
            'updateAction' => ['required', Rule::in(InventoryUpdateQuantityType::values())],
            'quantity' => ['required', 'integer'],
            'inventoryLocationId' => ['required', 'integer'],
            'inventoryItemId' => ['required', 'integer'],
            'type' => ['required', Rule::in([...$allowedTypes, 'onHand'])],
            'note' => ['string'],
        ];
    }
}
