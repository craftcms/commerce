<?php

declare(strict_types=1);

use CraftCms\Commerce\Store\Data\StoreSettings;

test('setCountries normalizes JSON/array input and rejects anything else', function(mixed $countries, bool $expectException, array $expected) {
    $store = new StoreSettings();

    if ($expectException) {
        expect(fn() => $store->setCountries($countries))->toThrow(InvalidArgumentException::class);
        return;
    }

    $store->setCountries($countries);

    expect($store->getCountries())->toEqual($expected);
})->with([
    [json_encode(['US', 'CA']), false, ['US', 'CA']],
    ['US', true, []],
    [['US', 'GB'], false, ['US', 'GB']],
]);

test('getCountriesList returns country names keyed by code, for the selected countries only', function(array $countries, array $expected) {
    $store = new StoreSettings();
    $store->setCountries($countries);

    expect($store->getCountriesList())->toEqual($expected);
})->with([
    [['US', 'GB', 'LV'], ['LV' => 'Latvia', 'GB' => 'United Kingdom', 'US' => 'United States']],
    [['US', 'GB'], ['GB' => 'United Kingdom', 'US' => 'United States']],
    [['US'], ['US' => 'United States']],
    [['XX'], []],
    [[], []],
]);

test('getAdministrativeAreasListByCountryCode returns subdivisions keyed by country, for the selected countries only', function(array $countries, array $expected) {
    $store = new StoreSettings();
    $store->setCountries($countries);

    expect($store->getAdministrativeAreasListByCountryCode())->toEqual($expected);
})->with([
    [[], []],
    [['AU'], [
        'AU' => [
            'ACT' => 'Australian Capital Territory',
            'NSW' => 'New South Wales',
            'NT' => 'Northern Territory',
            'QLD' => 'Queensland',
            'SA' => 'South Australia',
            'TAS' => 'Tasmania',
            'VIC' => 'Victoria',
            'WA' => 'Western Australia',
        ],
    ]],
]);
