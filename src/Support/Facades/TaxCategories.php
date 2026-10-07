<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Tax\Data\TaxCategory[] getAllTaxCategories(bool $withTrashed = false)
 * @method static \CraftCms\Commerce\Tax\Data\TaxCategory|null getTaxCategoryById(int $taxCategoryId)
 * @method static \CraftCms\Commerce\Tax\Data\TaxCategory|null getTaxCategoryByHandle(string $taxCategoryHandle)
 * @method static array getAllTaxCategoriesAsList()
 * @method static \CraftCms\Commerce\Tax\Data\TaxCategory getDefaultTaxCategory()
 * @method static bool saveTaxCategory(\CraftCms\Commerce\Tax\Data\TaxCategory $taxCategory, bool $runValidation = true)
 * @method static bool deleteTaxCategoryById(int $id)
 * @method static array getTaxCategoriesByProductTypeId(int $productTypeId)
 *
 * @see \CraftCms\Commerce\Tax\TaxCategories
 */
class TaxCategories extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Tax\TaxCategories::class;
    }
}
