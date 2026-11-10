<?php

declare(strict_types=1);

use CraftCms\Commerce\Helpers\Cp;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;

it('renders component select fields', function(string $method) {
    $html = Cp::$method(['label' => 'Things', 'name' => 'things']);

    expect($html)
        ->toContain('<craft-component-select')
        ->toContain('name="things"')
        ->toContain('Things');
})->with([
    'inventoryLocationFieldHtml',
    'taxZoneFieldHtml',
    'taxCategoryFieldHtml',
    'shippingCategoryFieldHtml',
]);

it('defaults the options and create action', function() {
    $taxCategory = app(TaxCategories::class)->getAllTaxCategories()[0];
    $shippingCategory = app(ShippingCategories::class)->getAllShippingCategories()->first();

    expect(Cp::taxCategoryFieldHtml(['name' => 'taxCategoryId', 'storeHandle' => 'primary']))
        ->toContain(e($taxCategory->name))
        ->toContain('commerce/tax-categories/edit?storeHandle=primary')
        ->and(Cp::shippingCategoryFieldHtml(['name' => 'shippingCategoryId', 'create' => false]))
        ->toContain(e($shippingCategory->name))
        ->not->toContain('commerce/shipping-categories/edit');
});
