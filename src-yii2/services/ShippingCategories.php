<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Shipping\Models\ShippingCategory;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Shipping\ShippingCategories::class)` instead.
 */
class ShippingCategories extends Component
{
    /**
     * @return Collection<int, ShippingCategory>
     */
    public function getAllShippingCategories(?int $storeId = null, bool $withTrashed = false): Collection
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->getAllShippingCategories($storeId, $withTrashed);
    }

    /**
     * @return array<int, string>
     */
    public function getAllShippingCategoriesAsList(?int $storeId = null): array
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->getAllShippingCategoriesAsList($storeId);
    }

    public function getShippingCategoryById(int $shippingCategoryId, ?int $storeId = null): ?ShippingCategory
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->getShippingCategoryById($shippingCategoryId, $storeId);
    }

    public function getShippingCategoryByHandle(string $shippingCategoryHandle, ?int $storeId = null): ?ShippingCategory
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->getShippingCategoryByHandle($shippingCategoryHandle, $storeId);
    }

    public function getDefaultShippingCategory(int $storeId): ShippingCategory
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->getDefaultShippingCategory($storeId);
    }

    public function saveShippingCategory(ShippingCategory $shippingCategory, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->saveShippingCategory($shippingCategory, $runValidation);
    }

    public function deleteShippingCategoryById(int $id): bool
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->deleteShippingCategoryById($id);
    }

    /**
     * @return array<int, ShippingCategory>
     */
    public function getShippingCategoriesByProductTypeId(int $productTypeId): array
    {
        return app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->getShippingCategoriesByProductTypeId($productTypeId);
    }

    public function clearCaches(): void
    {
        app(\CraftCms\Commerce\Shipping\ShippingCategories::class)->clearCaches();
    }
}
