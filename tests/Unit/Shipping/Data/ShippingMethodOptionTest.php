<?php

declare(strict_types=1);

use CraftCms\Commerce\Shipping\Data\ShippingMethodOption;

it('excludes dateCreated and dateUpdated from serialization', function() {
    $option = new ShippingMethodOption();
    $option->price = 0.0;
    $option->matchesOrder = false;

    expect($option->fields())->not->toHaveKeys(['dateCreated', 'dateUpdated'])
        ->and($option->toArray())->not->toHaveKeys(['dateCreated', 'dateUpdated']);
});
