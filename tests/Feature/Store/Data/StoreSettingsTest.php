<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Commerce\Store\Stores;

test('getLocationAddress lazily creates a default US location address when the store has none yet', function() {
    $store = app(Stores::class)->getPrimaryStore();
    $address = $store->getSettings()->getLocationAddress();

    expect($address)->toBeInstanceOf(Address::class)
        ->and($address->countryCode)->toBe('US')
        ->and($address->title)->toBe('Store');
});
