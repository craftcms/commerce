<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tax;

use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Tax\Models\TaxCategory;
use CraftCms\Commerce\Tax\Records\TaxCategory as TaxCategoryRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use function CraftCms\Cms\t;

#[Singleton]
class TaxCategories
{
    /** @var TaxCategory[]|null */
    private ?array $allTaxCategories = null;

    /** @var TaxCategory[]|null */
    private ?array $allTaxCategoriesWithTrashed = null;

    /**
     * @return TaxCategory[]
     */
    public function getAllTaxCategories(bool $withTrashed = false): array
    {
        if ($this->allTaxCategories === null || $this->allTaxCategoriesWithTrashed === null) {
            $rows = $this->query(true)->get()->all();

            $this->allTaxCategories = [];
            $this->allTaxCategoriesWithTrashed = [];
            foreach ($rows as $row) {
                $taxCategory = new TaxCategory((array) $row);

                if (!$taxCategory->dateDeleted) {
                    $this->allTaxCategories[] = $taxCategory;
                }
                $this->allTaxCategoriesWithTrashed[] = $taxCategory;
            }
        }

        return $withTrashed ? $this->allTaxCategoriesWithTrashed : $this->allTaxCategories;
    }

    public function getTaxCategoryById(int $taxCategoryId): ?TaxCategory
    {
        return collect($this->getAllTaxCategories())->firstWhere('id', $taxCategoryId);
    }

    public function getTaxCategoryByHandle(string $taxCategoryHandle): ?TaxCategory
    {
        return collect($this->getAllTaxCategories())->firstWhere('handle', $taxCategoryHandle);
    }

    /**
     * @return array<int, string>
     */
    public function getAllTaxCategoriesAsList(): array
    {
        return collect($this->getAllTaxCategories())
            ->mapWithKeys(fn(TaxCategory $c) => [$c->id => $c->getUiLabel()])
            ->all();
    }

    /**
     * @throws \RuntimeException
     */
    public function getDefaultTaxCategory(): TaxCategory
    {
        $categories = $this->getAllTaxCategories();

        $default = collect($categories)->firstWhere('default', true)
            ?? collect($categories)->first();

        if (!$default) {
            throw new \RuntimeException('Commerce must have at least one (default) tax category set up.');
        }

        return $default;
    }

    public function saveTaxCategory(TaxCategory $taxCategory, bool $runValidation = true): bool
    {
        if ($taxCategory->id) {
            $record = TaxCategoryRecord::find($taxCategory->id);

            if (!$record) {
                throw new \RuntimeException(t('No tax category exists with the ID “{id}”', ['id' => $taxCategory->id], category: 'commerce'));
            }
        } else {
            $record = new TaxCategoryRecord();
        }

        if ($runValidation && !$taxCategory->validate()) {
            return false;
        }

        $record->name = $taxCategory->name;
        $record->handle = $taxCategory->handle;
        $record->description = $taxCategory->description;
        $record->icon = $taxCategory->icon;
        $record->color = $taxCategory->color;
        $record->default = $taxCategory->default;

        $record->save();

        $taxCategory->id = $record->id;

        // If this was the default, clear default on all others.
        if ($taxCategory->default) {
            TaxCategoryRecord::withTrashed()->where('id', '!=', $record->id)->update(['default' => false]);
        }

        $currentProductTypeIds = DB::table(Table::PRODUCTTYPES_TAXCATEGORIES)
            ->where('taxCategoryId', $taxCategory->id)
            ->pluck('productTypeId')
            ->all();

        $newProductTypeIds = collect($taxCategory->getProductTypes())->pluck('id')->all();

        foreach (array_diff($currentProductTypeIds, $newProductTypeIds) as $oldProductTypeId) {
            $this->resaveProductsByProductTypeId((int) $oldProductTypeId);
        }
        foreach (array_diff($newProductTypeIds, $currentProductTypeIds) as $newProductTypeId) {
            $this->resaveProductsByProductTypeId((int) $newProductTypeId);
        }

        DB::table(Table::PRODUCTTYPES_TAXCATEGORIES)
            ->where('taxCategoryId', $record->id)
            ->delete();

        $now = now()->toDateTimeString();
        foreach ($taxCategory->getProductTypes() as $productType) {
            DB::table(Table::PRODUCTTYPES_TAXCATEGORIES)->insert([
                'productTypeId' => (int) $productType->id,
                'taxCategoryId' => $taxCategory->id,
                'dateCreated' => $now,
                'dateUpdated' => $now,
            ]);
        }

        $this->allTaxCategories = null;

        return true;
    }

    public function deleteTaxCategoryById(int $id): bool
    {
        $taxCategory = TaxCategoryRecord::find($id);

        if ($taxCategory === null || $taxCategory->default) {
            return false;
        }

        if ($taxCategory->delete()) {
            $this->allTaxCategories = null;
            return true;
        }

        return false;
    }

    /**
     * @return array<int, TaxCategory>
     */
    public function getTaxCategoriesByProductTypeId(int $productTypeId): array
    {
        $rows = $this->query()
            ->join(Table::PRODUCTTYPES_TAXCATEGORIES . ' as productTypeTaxCategories', 'taxCategories.id', '=', 'productTypeTaxCategories.taxCategoryId')
            ->where('productTypeTaxCategories.productTypeId', $productTypeId)
            ->get()
            ->all();

        if (empty($rows)) {
            try {
                $taxCategory = $this->getDefaultTaxCategory();
            } catch (\RuntimeException) {
                return [];
            }

            return [$taxCategory->id => $taxCategory];
        }

        $taxCategories = [];
        foreach ($rows as $row) {
            $taxCategory = new TaxCategory((array) $row);
            $taxCategories[$taxCategory->id] = $taxCategory;
        }

        return $taxCategories;
    }

    private function resaveProductsByProductTypeId(int $productTypeId): void
    {
        dispatch(new ResaveElements(
            elementType: Product::class,
            criteria: [
                'typeId' => $productTypeId,
                'siteId' => '*',
                'unique' => true,
                'status' => null,
            ],
        ));
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function query(bool $withTrashed = false): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(Table::TAXCATEGORIES . ' as taxCategories')
            ->select([
                'taxCategories.dateCreated',
                'taxCategories.dateDeleted',
                'taxCategories.dateUpdated',
                'taxCategories.default',
                'taxCategories.description',
                'taxCategories.handle',
                'taxCategories.id',
                'taxCategories.name',
            ]);

        // Only add icon and color if the columns exist (for pre-migration compatibility).
        if (Schema::hasColumn(Table::TAXCATEGORIES, 'icon')) {
            $query->addSelect(['taxCategories.icon', 'taxCategories.color']);
        }

        if (!$withTrashed) {
            $query->whereNull('dateDeleted');
        }

        return $query;
    }
}
