<?php

declare(strict_types=1);

use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Shipping\Models\ShippingCategory as ShippingCategoryRecord;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Stores;

test('deleteShippingCategoryById soft-deletes a non-default shipping category', function() {
    $storeId = app(Stores::class)->getPrimaryStore()->id;

    $shippingCategory = new ShippingCategory();
    $shippingCategory->storeId = $storeId;
    $shippingCategory->name = 'Another Shipping Category';
    $shippingCategory->handle = 'anotherShippingCategory';
    if (!app(ShippingCategories::class)->saveShippingCategory($shippingCategory)) {
        throw new RuntimeException('Could not save shipping category: ' . json_encode($shippingCategory->errors()->all()));
    }

    expect($shippingCategory->default)->toBeFalse();

    $result = app(ShippingCategories::class)->deleteShippingCategoryById($shippingCategory->id);

    expect($result)->toBeTrue()
        ->and(app(ShippingCategories::class)->getShippingCategoryById($shippingCategory->id))->toBeNull()
        ->and(ShippingCategoryRecord::withTrashed()->find($shippingCategory->id))->not->toBeNull();
});
