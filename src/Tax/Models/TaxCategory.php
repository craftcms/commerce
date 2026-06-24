<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax\Models;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Component\Contracts\Chippable;
use CraftCms\Cms\Component\Contracts\Colorable;
use CraftCms\Cms\Component\Contracts\Iconic;
use CraftCms\Cms\Shared\Enums\Color;
use DateTime;
use Illuminate\Support\Collection;

class TaxCategory extends Component implements Chippable, Colorable, Iconic
{
    public ?int $id = null;

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
        // TODO: migrate to app(TaxCategories::class)->getTaxCategoryById() once service migrated to src/
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($id);
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

    public function getTaxRates(?int $storeId = null): Collection
    {
        return Plugin::getInstance()->getTaxRates()->getAllTaxRates($storeId)->where('taxCategoryId', $this->id);
    }

    public function getCpEditUrl(?int $storeId = null): string
    {
        if ($storeId === null || !$store = Plugin::getInstance()->getStores()->getStoreById($storeId)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        return $store->getStoreSettingsUrl('taxcategories/' . $this->id);
    }

    public function setProductTypes(array $productTypes): void
    {
        $this->_productTypes = $productTypes;
    }

    public function getProductTypes(): array
    {
        if ($this->_productTypes === null && $this->id) {
            $this->_productTypes = Plugin::getInstance()->getProductTypes()->getProductTypesByTaxCategoryId($this->id);
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
            'handle' => ['required', 'string', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
        ];
    }

    #[\Override]
    public function extraFields(): array
    {
        return array_merge(parent::extraFields(), ['productTypes', 'productTypeIds', 'taxRates']);
    }
}
