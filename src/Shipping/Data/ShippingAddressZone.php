<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Data;

use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Base\Zone;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\ShippingZones;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class ShippingAddressZone extends Zone implements Chippable
{
    #[\Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['name'][] = Rule::unique(Table::SHIPPINGZONES, 'name')->where('storeId', $this->storeId);

        return $rules;
    }

    #[\Override]
    public function getCpEditUrl(): string
    {
        return Url::cpUrl('commerce/store-management/' . $this->getStore()->handle . '/shippingzones/' . $this->id);
    }

    #[\Override]
    public static function get(int|string $id): ?static
    {
        // TODO: migrate to app(ShippingZones::class)->getShippingZoneById() once service migrated to src/
        foreach (app(Stores::class)->getAllStores() as $store) {
            $zone = app(ShippingZones::class)->getShippingZoneById((int)$id, $store->id);
            if ($zone !== null) {
                /** @phpstan-ignore-next-line */
                return $zone;
            }
        }
        return null;
    }

    #[\Override]
    public function getUiLabel(): string
    {
        return t($this->name ?? '', category: 'site');
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }
}
