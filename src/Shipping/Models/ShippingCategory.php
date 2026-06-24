<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Models;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Component\Contracts\Colorable;
use CraftCms\Cms\Component\Contracts\Iconic;
use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;

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
        $storeId = $site ? Plugin::getInstance()->getStores()->getStoreBySiteId($site->id)?->id : null;

        // TODO: migrate to app(ShippingCategories::class)->getShippingCategoryById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id, $storeId);
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
    public function getStore(): \craft\commerce\models\Store
    {
        if (!$store = Plugin::getInstance()->getStores()->getStoreById($this->storeId)) {
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
            $this->_productTypes = Plugin::getInstance()->getProductTypes()->getProductTypesByShippingCategoryId($this->id);
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
