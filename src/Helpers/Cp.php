<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tax\TaxZones;

class Cp
{
    public static function inventoryLocationFieldHtml(array $config): string
    {
        $config['id'] ??= 'inventorylocationselect' . mt_rand();
        $config['options'] ??= app(InventoryLocations::class)->getAllInventoryLocations()->all();
        $config['createAction'] = ($config['create'] ?? false) ? 'commerce/inventory-locations/edit' : null;

        return self::componentSelectFieldHtml($config);
    }

    public static function taxZoneFieldHtml(array $config): string
    {
        $config['id'] ??= 'taxzoneselect' . mt_rand();
        $config['options'] ??= app(TaxZones::class)->getAllTaxZones($config['storeId'] ?? null)->all();
        $config['createAction'] = self::createAction('commerce/tax-zones/edit', $config);

        return self::componentSelectFieldHtml($config);
    }

    public static function taxCategoryFieldHtml(array $config): string
    {
        $config['id'] ??= 'taxcategoryselect' . mt_rand();
        $config['options'] ??= app(TaxCategories::class)->getAllTaxCategories();
        $config['createAction'] = self::createAction('commerce/tax-categories/edit', $config);

        return self::componentSelectFieldHtml($config);
    }

    public static function shippingCategoryFieldHtml(array $config): string
    {
        $config['id'] ??= 'shippingcategoryselect' . mt_rand();
        $config['options'] ??= app(ShippingCategories::class)->getAllShippingCategories($config['storeId'] ?? null)->all();
        $config['createAction'] = self::createAction('commerce/shipping-categories/edit', $config);

        return self::componentSelectFieldHtml($config);
    }

    private static function createAction(string $action, array $config): ?string
    {
        if (!($config['create'] ?? true)) {
            return null;
        }

        return empty($config['storeHandle']) ? $action : $action . '?storeHandle=' . $config['storeHandle'];
    }

    private static function componentSelectFieldHtml(array $config): string
    {
        return FormFields::fieldHtml(
            fn(array $c): string => FormFields::componentSelectFromConfig($c)->toHtml(),
            $config,
        );
    }
}
