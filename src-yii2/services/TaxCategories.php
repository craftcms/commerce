<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Tax\Models\TaxCategory;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\TaxCategories::class)` instead.
 */
class TaxCategories extends Component
{
    /**
     * @return TaxCategory[]
     */
    public function getAllTaxCategories(bool $withTrashed = false): array
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->getAllTaxCategories($withTrashed);
    }

    public function getTaxCategoryById(int $taxCategoryId): ?TaxCategory
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->getTaxCategoryById($taxCategoryId);
    }

    public function getTaxCategoryByHandle(string $taxCategoryHandle): ?TaxCategory
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->getTaxCategoryByHandle($taxCategoryHandle);
    }

    /**
     * @return array<int, string>
     */
    public function getAllTaxCategoriesAsList(): array
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->getAllTaxCategoriesAsList();
    }

    public function getDefaultTaxCategory(): TaxCategory
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->getDefaultTaxCategory();
    }

    public function saveTaxCategory(TaxCategory $taxCategory, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->saveTaxCategory($taxCategory, $runValidation);
    }

    public function deleteTaxCategoryById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->deleteTaxCategoryById($id);
    }

    /**
     * @return array<int, TaxCategory>
     */
    public function getTaxCategoriesByProductTypeId(int $productTypeId): array
    {
        return app(\CraftCms\Commerce\Services\TaxCategories::class)->getTaxCategoriesByProductTypeId($productTypeId);
    }
}
