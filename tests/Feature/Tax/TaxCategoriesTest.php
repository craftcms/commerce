<?php

declare(strict_types=1);

use CraftCms\Commerce\Tax\Models\TaxCategory as TaxCategoryRecord;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;

test('deleteTaxCategoryById soft-deletes a tax category assigned to a variant', function() {
    $fixture = VariantQueryFixture::seed();
    $taxCategoryId = $fixture->whiteVariant->getTaxCategory()->id;

    $result = app(TaxCategories::class)->deleteTaxCategoryById($taxCategoryId);

    expect($result)->toBeTrue()
        ->and(TaxCategoryRecord::find($taxCategoryId))->toBeNull()
        ->and(TaxCategoryRecord::onlyTrashed()->where('id', $taxCategoryId)->first())->toBeInstanceOf(TaxCategoryRecord::class);
});

test('deleteTaxCategoryById refuses to delete the default tax category', function() {
    $default = app(TaxCategories::class)->getDefaultTaxCategory();

    $result = app(TaxCategories::class)->deleteTaxCategoryById($default->id);

    expect($result)->toBeFalse()
        ->and(TaxCategoryRecord::find($default->id))->not->toBeNull();
});
