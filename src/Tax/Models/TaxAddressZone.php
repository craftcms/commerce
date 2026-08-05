<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Models;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Commerce\Base\Zone;
use CraftCms\Commerce\Database\Table;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class TaxAddressZone extends Zone implements Chippable
{
    public bool $default = false;

    #[\Override]
    public static function get(int|string $id): ?static
    {
        foreach (Plugin::getInstance()->getStores()->getAllStores() as $store) {
            /** @phpstan-ignore-next-line */
            $zone = app(\CraftCms\Commerce\Services\TaxZones::class)->getTaxZoneById((int)$id, $store->id);
            if ($zone !== null) {
                /** @phpstan-ignore-next-line */
                return $zone;
            }
        }
        return null;
    }

    #[\Override]
    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('taxzones/' . $this->id);
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

    #[\Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['name'][] = Rule::unique(Table::TAXZONES, 'name')->where('storeId', $this->storeId);

        return $rules;
    }
}
