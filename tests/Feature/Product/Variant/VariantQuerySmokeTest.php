<?php

declare(strict_types=1);

use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

/**
 * Closes a coverage gap for VariantQuery's own scopes (typeId, productStatus, editable), which had
 * no dedicated tests before or after the Concerns/ trait extraction that moved their logic out of
 * private instance methods — confirms the refactor didn't silently change behavior.
 */
beforeEach(function() {
    $this->fixture = ProductConditionsFixture::seed();
});

test('typeId filters variants by owning product type', function() {
    $ids = Variant::find()->typeId($this->fixture->hoodiesType->id)->ids();

    expect($ids)->toContain($this->fixture->hoodieVariant->id);
    expect($ids)->not->toContain($this->fixture->tShirtVariant->id);
});

test('productStatus filters variants by owning product status', function() {
    $this->fixture->tShirt->enabled = false;
    \CraftCms\Cms\Support\Facades\Elements::saveElement($this->fixture->tShirt, false);

    $ids = Variant::find()->productStatus('disabled')->ids();

    expect($ids)->toContain($this->fixture->tShirtVariant->id);
    expect($ids)->not->toContain($this->fixture->hoodieVariant->id);
});

test('editable aborts to an empty result set when there is no current user', function() {
    $ids = Variant::find()->editable()->ids();

    expect($ids)->toBe([]);
});
