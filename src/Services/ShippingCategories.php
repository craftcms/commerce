<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingCategory as ShippingCategoryRecord;
use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\Models\ShippingCategory;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use function CraftCms\Cms\t;

#[Singleton]
class ShippingCategories
{
    /** @var array<int, Collection<int, ShippingCategory>>|null */
    private ?array $allShippingCategories = null;

    /**
     * @return Collection<int, ShippingCategory>
     */
    public function getAllShippingCategories(?int $storeId = null, bool $withTrashed = false): Collection
    {
        $storeId ??= $this->currentStoreId();

        if ($this->allShippingCategories === null || !isset($this->allShippingCategories[$storeId])) {
            $rows = $this->query(true)->where('storeId', $storeId)->get()->all();

            $this->allShippingCategories ??= [];

            foreach ($rows as $row) {
                $shippingCategory = new ShippingCategory((array) $row);
                $this->allShippingCategories[$shippingCategory->storeId] ??= collect();
                $this->allShippingCategories[$shippingCategory->storeId]->push($shippingCategory);
            }
        }

        if (!isset($this->allShippingCategories[$storeId])) {
            return collect();
        }

        return $this->allShippingCategories[$storeId]->filter(
            fn(ShippingCategory $sc) => $withTrashed || $sc->dateDeleted === null,
        );
    }

    /**
     * @return array<int, string>
     */
    public function getAllShippingCategoriesAsList(?int $storeId = null): array
    {
        return $this->getAllShippingCategories($storeId)
            ->mapWithKeys(fn(ShippingCategory $category) => [$category->id => $category->getUiLabel()])
            ->all();
    }

    public function getShippingCategoryById(int $shippingCategoryId, ?int $storeId = null): ?ShippingCategory
    {
        return $this->getAllShippingCategories($storeId)->firstWhere('id', $shippingCategoryId);
    }

    public function getShippingCategoryByHandle(string $shippingCategoryHandle, ?int $storeId = null): ?ShippingCategory
    {
        return $this->getAllShippingCategories($storeId)->firstWhere('handle', $shippingCategoryHandle);
    }

    public function getDefaultShippingCategory(int $storeId): ShippingCategory
    {
        $categories = $this->getAllShippingCategories($storeId);
        $default = $categories->firstWhere('default', true) ?? $categories->first();

        if (!$default) {
            throw new \RuntimeException('Commerce must have at least one (default) shipping category set up.');
        }

        return $default;
    }

    public function saveShippingCategory(ShippingCategory $shippingCategory, bool $runValidation = true): bool
    {
        if ($shippingCategory->id) {
            /** @phpstan-ignore-next-line */
            $record = ShippingCategoryRecord::findOne($shippingCategory->id);
            if (!$record) {
                throw new \RuntimeException(t('No shipping category exists with the ID “{id}”', ['id' => $shippingCategory->id], category: 'commerce'));
            }
        } else {
            $record = new ShippingCategoryRecord();
        }

        if ($runValidation && !$shippingCategory->validate()) {
            return false;
        }

        $record->name = $shippingCategory->name;
        $record->storeId = $shippingCategory->storeId;
        $record->handle = $shippingCategory->handle;
        $record->description = $shippingCategory->description;
        $record->icon = $shippingCategory->icon;
        $record->color = $shippingCategory->color;
        $record->default = $shippingCategory->default;

        $record->save(false);

        $shippingCategory->id = $record->id;

        // If this was the default, clear default on all others in the same store.
        if ($shippingCategory->default) {
            /** @phpstan-ignore-next-line */
            ShippingCategoryRecord::updateAll(
                ['default' => false],
                ['and', ['storeId' => $record->storeId], ['not', ['id' => $record->id]]],
            );
        }

        $currentProductTypeIds = DB::table(Table::PRODUCTTYPES_SHIPPINGCATEGORIES)
            ->where('shippingCategoryId', $shippingCategory->id)
            ->pluck('productTypeId')
            ->all();

        $newProductTypeIds = collect($shippingCategory->getProductTypes())->pluck('id')->all();

        $removedProductTypeIds = array_diff($currentProductTypeIds, $newProductTypeIds);

        // Update purchasables to the default shipping category when product types are removed.
        if (!empty($removedProductTypeIds)) {
            $defaultShippingCategory = $this->getDefaultShippingCategory($shippingCategory->storeId);

            $purchasableIds = DB::table(Table::PURCHASABLES_STORES . ' as ps')
                ->join(Table::VARIANTS . ' as v', 'ps.purchasableId', '=', 'v.id')
                ->join(Table::PRODUCTS . ' as p', 'v.primaryOwnerId', '=', 'p.id')
                ->where('ps.shippingCategoryId', $shippingCategory->id)
                ->where('ps.storeId', $shippingCategory->storeId)
                ->whereIn('p.typeId', $removedProductTypeIds)
                ->pluck('ps.purchasableId')
                ->all();

            if (!empty($purchasableIds)) {
                DB::table(Table::PURCHASABLES_STORES)
                    ->whereIn('purchasableId', $purchasableIds)
                    ->where('storeId', $shippingCategory->storeId)
                    ->where('shippingCategoryId', $shippingCategory->id)
                    ->update(['shippingCategoryId' => $defaultShippingCategory->id]);
            }
        }

        foreach (array_diff($currentProductTypeIds, $newProductTypeIds) as $oldProductTypeId) {
            $this->resaveVariantsByProductTypeId((int) $oldProductTypeId);
        }
        foreach (array_diff($newProductTypeIds, $currentProductTypeIds) as $newProductTypeId) {
            $this->resaveVariantsByProductTypeId((int) $newProductTypeId);
        }

        DB::table(Table::PRODUCTTYPES_SHIPPINGCATEGORIES)
            ->where('shippingCategoryId', $shippingCategory->id)
            ->delete();

        foreach ($shippingCategory->getProductTypes() as $productType) {
            DB::table(Table::PRODUCTTYPES_SHIPPINGCATEGORIES)->insert([
                'productTypeId' => (int) $productType->id,
                'shippingCategoryId' => (int) $shippingCategory->id,
            ]);
        }

        $this->allShippingCategories = null;

        return true;
    }

    public function deleteShippingCategoryById(int $id): bool
    {
        /** @phpstan-ignore-next-line */
        $shippingCategory = ShippingCategoryRecord::findOne($id);

        if ($shippingCategory === null || $shippingCategory->default) {
            return false;
        }

        if ($shippingCategory->softDelete()) {
            $this->allShippingCategories = null;
            return true;
        }

        return false;
    }

    /**
     * @return array<int, ShippingCategory>
     */
    public function getShippingCategoriesByProductTypeId(int $productTypeId): array
    {
        $rows = $this->query()
            ->join(Table::PRODUCTTYPES_SHIPPINGCATEGORIES . ' as productTypeShippingCategories', 'shippingCategories.id', '=', 'productTypeShippingCategories.shippingCategoryId')
            ->where('productTypeShippingCategories.productTypeId', $productTypeId)
            ->get()
            ->all();

        if (empty($rows)) {
            try {
                $shippingCategory = $this->getAllShippingCategories()->firstWhere('default', true);
            } catch (\RuntimeException) {
                return [];
            }
            if (!$shippingCategory) {
                return [];
            }

            return [$shippingCategory->id => $shippingCategory];
        }

        $shippingCategories = [];
        foreach ($rows as $row) {
            $shippingCategory = new ShippingCategory((array) $row);
            $shippingCategories[$shippingCategory->id] = $shippingCategory;
        }

        return $shippingCategories;
    }

    public function clearCaches(): void
    {
        $this->allShippingCategories = null;
    }

    private function resaveVariantsByProductTypeId(int $productTypeId): void
    {
        dispatch(new ResaveElements(
            elementType: Variant::class, /** @phpstan-ignore-line */
            criteria: [
                'typeId' => $productTypeId,
                'siteId' => '*',
                'unique' => true,
                'status' => null,
            ],
            updateSearchIndex: false,
        ));
    }

    private function query(bool $withTrashed = false): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(Table::SHIPPINGCATEGORIES . ' as shippingCategories')
            ->select([
                'shippingCategories.dateCreated',
                'shippingCategories.dateDeleted',
                'shippingCategories.dateUpdated',
                'shippingCategories.default',
                'shippingCategories.description',
                'shippingCategories.handle',
                'shippingCategories.id',
                'shippingCategories.name',
                'shippingCategories.storeId',
            ]);

        // Only add icon and color if the columns exist (for pre-migration compatibility).
        if (Schema::hasColumn(Table::SHIPPINGCATEGORIES, 'icon')) {
            $query->addSelect(['shippingCategories.icon', 'shippingCategories.color']);
        }

        if (!$withTrashed) {
            $query->whereNull('dateDeleted');
        }

        return $query;
    }

    private function currentStoreId(): int
    {
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getStores()->getCurrentStore()->id;
    }
}
