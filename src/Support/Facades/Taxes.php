<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getTaxIdValidators()
 * @method static \Illuminate\Support\Collection getEnabledTaxIdValidators()
 * @method static \CraftCms\Commerce\Tax\Contracts\TaxEngineInterface getEngine()
 * @method static string displayName()
 * @method static bool isSelectable()
 * @method static string taxAdjusterClass()
 * @method static bool viewTaxCategories()
 * @method static bool createTaxCategories()
 * @method static bool editTaxCategories()
 * @method static bool deleteTaxCategories()
 * @method static string taxCategoryActionHtml()
 * @method static bool viewTaxZones()
 * @method static bool editTaxZones()
 * @method static bool viewTaxRates()
 * @method static bool editTaxRates()
 * @method static array cpTaxNavSubItems()
 * @method static bool createTaxZones()
 * @method static bool deleteTaxZones()
 * @method static string taxZoneActionHtml()
 * @method static bool createTaxRates()
 * @method static bool deleteTaxRates()
 * @method static string taxRateActionHtml()
 *
 * @see \CraftCms\Commerce\Tax\Taxes
 */
class Taxes extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Tax\Taxes::class;
    }
}
