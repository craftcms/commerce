<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use craft\helpers\Cp as CraftCp;

class Cp
{
    public static function inventoryLocationFieldHtml(array $config): string
    {
        $config['id'] ??= 'inventorylocationselect' . mt_rand();
        return CraftCp::fieldHtml('template:commerce/_includes/forms/inventoryLocationSelect.twig', $config);
    }

    public static function taxZoneFieldHtml(array $config): string
    {
        $config['id'] ??= 'taxzoneselect' . mt_rand();
        return CraftCp::fieldHtml('template:commerce/_includes/forms/taxZoneSelect.twig', $config);
    }

    public static function taxCategoryFieldHtml(array $config): string
    {
        $config['id'] ??= 'taxcategoryselect' . mt_rand();
        return CraftCp::fieldHtml('template:commerce/_includes/forms/taxCategorySelect.twig', $config);
    }

    public static function shippingCategoryFieldHtml(array $config): string
    {
        $config['id'] ??= 'shippingcategoryselect' . mt_rand();
        return CraftCp::fieldHtml('template:commerce/_includes/forms/shippingCategorySelect.twig', $config);
    }
}
