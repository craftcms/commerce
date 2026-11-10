<?php

declare(strict_types=1);

use CraftCms\Cms\View\TemplateManager;
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

it('renders the component select templates', function(string $template) {
    $html = app(TemplateManager::class)->renderTemplate("commerce/_includes/forms/$template", [
        'name' => 'things',
        'storeId' => 1,
        'create' => true,
    ]);

    expect($html)
        ->toContain('<craft-component-select')
        ->toContain('name="things"')
        ->toContain('create-action');
})->with([
    'inventoryLocationSelect',
    'taxZoneSelect',
    'taxCategorySelect',
    'shippingCategorySelect',
]);
