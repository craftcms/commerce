<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Component\Contracts\Colorable;
use CraftCms\Cms\Component\Contracts\Iconic;
use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Shipping\ShippingCategories;

use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use CraftCms\Commerce\Store\Stores;
use DateTime;
use function CraftCms\Cms\t;

class ShippingCategory extends Component implements HasStoreInterface, Chippable, Colorable, Iconic
{
    public ?int $id = null;

    public ?int $storeId = null;

    public ?string $name = null;

    public ?string $handle = null;

    public ?string $icon = null;

    public ?string $color = null;

    public ?string $description = null;

    public bool $default = false;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    public ?DateTime $dateDeleted = null;

    private ?array $_productTypes = null;

    public function __toString(): string
    {
        return (string)$this->name;
    }

    #[\Override]
    public static function get(int|string $id): ?static
    {
        $site = app(RequestedSite::class)->get();
        $storeId = $site ? app(Stores::class)->getStoreBySiteId($site->id)?->id : null;

        // TODO: migrate to app(ShippingCategories::class)->getShippingCategoryById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return app(ShippingCategories::class)->getShippingCategoryById($id, $storeId);
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function getUiLabel(): string
    {
        return t($this->name ?? '', category: 'site');
    }

    #[\Override]
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    #[\Override]
    public function getColor(): ?Color
    {
        return $this->color ? Color::tryFrom($this->color) : null;
    }

    #[\Override]
    public function getStore(): \CraftCms\Commerce\Store\Models\Store
    {
        if (!$store = app(Stores::class)->getStoreById($this->storeId)) {
            throw new \InvalidArgumentException('Invalid store ID: ' . $this->storeId);
        }

        return $store;
    }

    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('shippingcategories/' . $this->id);
    }

    public function setProductTypes(array $productTypes): void
    {
        $this->_productTypes = $productTypes;
    }

    public function getProductTypes(): array
    {
        if (!isset($this->_productTypes) && $this->id) {
            $this->_productTypes = app(ProductTypes::class)->getProductTypesByShippingCategoryId($this->id);
        }

        return $this->_productTypes ?? [];
    }

    public function getProductTypeIds(): array
    {
        return array_column($this->getProductTypes(), 'id');
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'handle' => ['required', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
        ];
    }

    #[\Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), ['productTypes', 'productTypeIds', 'uiLabel']);
    }
}
