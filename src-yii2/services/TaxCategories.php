<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Services\TaxCategories as NewTaxCategories;
use CraftCms\Commerce\Tax\Models\TaxCategory;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\TaxCategories::class)` instead.
 */
class TaxCategories extends Component
{
    private function impl(): NewTaxCategories
    {
        return app(NewTaxCategories::class);
    }

    /**
     * @return TaxCategory[]
     */
    public function getAllTaxCategories(bool $withTrashed = false): array
    {
        return $this->impl()->getAllTaxCategories($withTrashed);
    }

    public function getTaxCategoryById(int $taxCategoryId): ?TaxCategory
    {
        return $this->impl()->getTaxCategoryById($taxCategoryId);
    }

    public function getTaxCategoryByHandle(string $taxCategoryHandle): ?TaxCategory
    {
        return $this->impl()->getTaxCategoryByHandle($taxCategoryHandle);
    }

    /**
     * @return array<int, string>
     */
    public function getAllTaxCategoriesAsList(): array
    {
        return $this->impl()->getAllTaxCategoriesAsList();
    }

    public function getDefaultTaxCategory(): TaxCategory
    {
        return $this->impl()->getDefaultTaxCategory();
    }

    public function saveTaxCategory(TaxCategory $taxCategory, bool $runValidation = true): bool
    {
        return $this->impl()->saveTaxCategory($taxCategory, $runValidation);
    }

    public function deleteTaxCategoryById(int $id): bool
    {
        return $this->impl()->deleteTaxCategoryById($id);
    }

    /**
     * @return array<int, TaxCategory>
     */
    public function getTaxCategoriesByProductTypeId(int $productTypeId): array
    {
        return $this->impl()->getTaxCategoriesByProductTypeId($productTypeId);
    }
}
