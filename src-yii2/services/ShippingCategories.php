<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Services\ShippingCategories as NewShippingCategories;
use CraftCms\Commerce\Shipping\Models\ShippingCategory;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\ShippingCategories::class)` instead.
 */
class ShippingCategories extends Component
{
    private function impl(): NewShippingCategories
    {
        return app(NewShippingCategories::class);
    }

    /**
     * @return Collection<int, ShippingCategory>
     */
    public function getAllShippingCategories(?int $storeId = null, bool $withTrashed = false): Collection
    {
        return $this->impl()->getAllShippingCategories($storeId, $withTrashed);
    }

    /**
     * @return array<int, string>
     */
    public function getAllShippingCategoriesAsList(?int $storeId = null): array
    {
        return $this->impl()->getAllShippingCategoriesAsList($storeId);
    }

    public function getShippingCategoryById(int $shippingCategoryId, ?int $storeId = null): ?ShippingCategory
    {
        return $this->impl()->getShippingCategoryById($shippingCategoryId, $storeId);
    }

    public function getShippingCategoryByHandle(string $shippingCategoryHandle, ?int $storeId = null): ?ShippingCategory
    {
        return $this->impl()->getShippingCategoryByHandle($shippingCategoryHandle, $storeId);
    }

    public function getDefaultShippingCategory(int $storeId): ShippingCategory
    {
        return $this->impl()->getDefaultShippingCategory($storeId);
    }

    public function saveShippingCategory(ShippingCategory $shippingCategory, bool $runValidation = true): bool
    {
        return $this->impl()->saveShippingCategory($shippingCategory, $runValidation);
    }

    public function deleteShippingCategoryById(int $id): bool
    {
        return $this->impl()->deleteShippingCategoryById($id);
    }

    /**
     * @return array<int, ShippingCategory>
     */
    public function getShippingCategoriesByProductTypeId(int $productTypeId): array
    {
        return $this->impl()->getShippingCategoriesByProductTypeId($productTypeId);
    }

    public function clearCaches(): void
    {
        $this->impl()->clearCaches();
    }
}
