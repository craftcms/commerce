<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Support\Facades\DB;
use function CraftCms\Cms\t;

class DeactivateInventoryLocation extends Component
{
    public InventoryLocation $inventoryLocation;

    public InventoryLocation $destinationInventoryLocation;

    #[\Override]
    public function getRules(): array
    {
        return [
            'inventoryLocation' => [
                'required',
                function(string $attribute, mixed $value, \Closure $fail) {
                    $exists = DB::table(Table::INVENTORYLOCATIONS)
                        ->where('id', $this->inventoryLocation->id)
                        ->whereNull('dateDeleted')
                        ->exists();

                    if (!$exists) {
                        $fail(t('Inventory location is already deactivated.', category: 'commerce'));
                    }
                },
                function(string $attribute, mixed $value, \Closure $fail) {
                    $stores = app(Stores::class)->getAllStores();
                    foreach ($stores as $store) {
                        $locations = $store->getInventoryLocations();
                        if ($locations->count() == 1 && $locations->contains('id', $this->inventoryLocation->id)) {
                            $fail(t('This is the last location for the {store} store.', ['store' => $store->getName()], category: 'commerce'));
                        }
                    }
                },
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($this->hasOutStandingCommittedStock()) {
                        $fail(t('Inventory location has committed stock, the order(s) must first be fulfilled.', category: 'commerce'));
                    }
                },
                function(string $attribute, mixed $value, \Closure $fail) {
                    if ($this->hasOutStandingIncomingStock()) {
                        $fail(t('Inventory location has incoming stock, the transfer(s) must first be completed.', category: 'commerce'));
                    }
                },
            ],
            'destinationInventoryLocation' => ['required'],
        ];
    }

    public function hasOutStandingCommittedStock(): bool
    {
        $committedTotal = app(Inventory::class)->getInventoryLocationLevels($this->inventoryLocation)
            ->sum('committedTotal');

        return $committedTotal > 0;
    }

    public function hasOutStandingIncomingStock(): bool
    {
        $incomingTotal = app(Inventory::class)->getInventoryLocationLevels($this->inventoryLocation)
            ->sum('incomingTotal');

        return $incomingTotal > 0;
    }
}
