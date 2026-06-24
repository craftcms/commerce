<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Base\Zone;
use CraftCms\Commerce\Database\Table;
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
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            $zone = Plugin::getInstance()->getShippingZones()->getShippingZoneById((int)$id, $store->id);
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
