<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Component\Contracts\Colorable;
use CraftCms\Cms\Component\Contracts\Iconic;
use CraftCms\Cms\Component\Contracts\Statusable;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use function CraftCms\Cms\t;

class ShippingMethod extends BaseShippingMethod implements Chippable, Colorable, Iconic, Statusable
{
    #[\Override]
    public function getType(): string
    {
        return t('Custom', category: 'commerce');
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function getName(): string
    {
        return (string)$this->name;
    }

    #[\Override]
    public function getHandle(): string
    {
        return (string)$this->handle;
    }

    #[\Override]
    public function getShippingRules(): Collection
    {
        if ($this->id === null) {
            return collect();
        }

        return app(\CraftCms\Commerce\Services\ShippingRules::class)->getAllShippingRulesByShippingMethodId($this->id);
    }

    #[\Override]
    public function getIsEnabled(): bool
    {
        return $this->enabled;
    }

    #[\Override]
    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('shippingmethods/' . $this->id);
    }

    public static function get(int|string $id): ?static
    {
        /** @phpstan-ignore-next-line */
        return app(ShippingMethods::class)->getShippingMethodById($id);
    }

    public function getUiLabel(): string
    {
        return t($this->name ?? '', category: 'site');
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique(Table::SHIPPINGMETHODS, 'name')->where('storeId', $this->storeId)->ignore($this->id),
            ],
            'handle' => [
                'required',
                'string',
                Rule::unique(Table::SHIPPINGMETHODS, 'handle')->where('storeId', $this->storeId)->ignore($this->id),
            ],
        ];
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColor(): ?Color
    {
        return $this->color ? Color::tryFrom($this->color) : null;
    }

    public static function statuses(): array
    {
        return [
            'enabled' => ['label' => t('Enabled', category: 'commerce'), 'color' => 'green'],
            'disabled' => ['label' => t('Disabled', category: 'commerce'), 'color' => 'red'],
        ];
    }

    public function getStatus(): ?string
    {
        return $this->enabled ? 'enabled' : 'disabled';
    }
}
