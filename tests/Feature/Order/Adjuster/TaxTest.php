<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Conditions\CountryConditionRule;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Commerce\Address\Conditions\ZoneAddressCondition;
use CraftCms\Commerce\Order\Adjuster\Tax;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Tax\Data\TaxAddressZone;
use CraftCms\Commerce\Tax\Data\TaxRate;

/**
 * Builds a zone matching a set of countries, exactly the way {@see \CraftCms\Commerce\Address\Conditions\ZoneAddressCondition}
 * is built and stored in the CP's tax zone screen.
 */
function taxZoneForCountries(array $countryCodes): TaxAddressZone
{
    $condition = new ZoneAddressCondition();
    $condition->addConditionRule(new CountryConditionRule(['operator' => 'in', 'values' => $countryCodes]));

    $zone = new TaxAddressZone();
    $zone->setCondition($condition);

    return $zone;
}

/**
 * Builds an in-memory `TaxRate`, without persisting it — {@see Tax::adjust()} never queries
 * the database for rates directly (that's `getTaxRates()`, mocked out below), so the adjuster's
 * own calculation logic can be exercised entirely against plain objects.
 */
function taxRate(array $item): TaxRate
{
    $rate = Mockery::mock(TaxRate::class)->makePartial();
    $rate->name = $item['name'];
    $rate->code = $item['code'];
    $rate->rate = $item['rate'];
    $rate->include = $item['include'];
    $rate->removeIncluded = $item['removeIncluded'] ?? false;
    $rate->taxIdValidators = $item['taxIdValidators'] ?? [];
    $rate->removeVatIncluded = $item['removeVatIncluded'] ?? false;
    $rate->taxable = $item['taxable'];
    $rate->taxCategoryId = $item['taxCategoryId'];
    $rate->enabled = true;

    if (isset($item['zoneCountries'])) {
        $rate->shouldReceive('getIsEverywhere')->andReturn(false);
        $rate->shouldReceive('getTaxZone')->andReturn(taxZoneForCountries($item['zoneCountries']));
    } else {
        $rate->shouldReceive('getIsEverywhere')->andReturn(true);
        $rate->shouldReceive('getTaxZone')->andReturn(null);
    }

    return $rate;
}

/**
 * Runs `Tax::adjust()` against an order built from the given address/line-item/tax-rate data,
 * mirroring the legacy `tests-yii2/unit/adjusters/TaxTest.php::dataCases()` fixtures. `getTaxRates()`
 * and `validateTaxIdNumber()` are mocked (both protected) so the adjuster's own tax-calculation
 * logic is isolated from the tax-rate-lookup and VAT-ID-validation-service concerns, which are
 * covered elsewhere.
 */
function adjustWithTaxRates(array $addressData, array $lineItemData, array $taxRateData): array
{
    $order = new Order();

    $address = new Address();
    $address->countryCode = $addressData['countryCode'];
    $address->organizationTaxId = $addressData['organizationTaxId'] ?? null;
    $order->setShippingAddress($address);

    $lineItems = [];
    foreach ($lineItemData as $item) {
        $lineItem = new LineItem();
        $lineItem->qty = $item['qty'];
        $lineItem->setPrice($item['price']);
        $lineItem->taxCategoryId = 1;
        $lineItems[] = $lineItem;
    }
    $order->setLineItems($lineItems);

    $taxAdjuster = Mockery::mock(Tax::class)->makePartial();
    $taxAdjuster->shouldAllowMockingProtectedMethods();
    $taxAdjuster->shouldReceive('getTaxRates')->andReturn(collect(array_map(taxRate(...), $taxRateData)));
    $taxAdjuster->shouldReceive('validateTaxIdNumber')->andReturn($addressData['_validateVat'] ?? false);

    $adjustments = $taxAdjuster->adjust($order);
    $order->setAdjustments($adjustments);

    return ['order' => $order, 'adjustments' => $adjustments];
}

test('tax adjustments', function(array $addressData, array $lineItemData, array $taxRateData, array $expected) {
    ['order' => $order, 'adjustments' => $adjustments] = adjustWithTaxRates($addressData, $lineItemData, $taxRateData);

    expect($adjustments)->toHaveCount(count($expected['adjustments']));

    foreach ($expected['adjustments'] as $index => $item) {
        expect($adjustments[$index]->type)->toBe($item['type']);
        expect(round($adjustments[$index]->amount, 2))->toEqual($item['amount']);
        expect($adjustments[$index]->included)->toBe($item['included']);
        expect($adjustments[$index]->description)->toBe($item['description']);
    }

    expect($order->getTotalQty())->toEqual($expected['orderTotalQty']);
    expect($order->getTotalPrice())->toEqual($expected['orderTotalPrice']);
    expect(round($order->getTotalTax(), 2))->toEqual($expected['orderTotalTax']);
    expect(round($order->getTotalTaxIncluded(), 2))->toEqual($expected['orderTotalTaxIncluded']);
})->with([
    'tax-10pct-included' => [
        ['countryCode' => 'AU'],
        [['price' => 100, 'qty' => 1]],
        [[
            'name' => 'Australia', 'code' => 'GST', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => true, 'taxable' => 'order_total_price', 'zoneCountries' => ['AU'],
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'amount' => 9.09, 'included' => true, 'description' => '10%'],
            ],
            'orderTotalPrice' => 100,
            'orderTotalQty' => 1,
            'orderTotalTax' => 0,
            'orderTotalTaxIncluded' => 9.09,
        ],
    ],

    'tax-10pct-not-included' => [
        ['countryCode' => 'AU'],
        [['price' => 100, 'qty' => 1]],
        [[
            'name' => 'Australia', 'code' => 'GST', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => false, 'taxable' => 'order_total_price',
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'amount' => 10, 'included' => false, 'description' => '10%'],
            ],
            'orderTotalPrice' => 110,
            'orderTotalQty' => 1,
            'orderTotalTax' => 10,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-10pct-included-2-line-items' => [
        ['countryCode' => 'NL'],
        [
            ['price' => 100, 'qty' => 1],
            ['price' => 50, 'qty' => 2],
        ],
        [[
            'name' => 'Netherlands', 'code' => 'NLVAT', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => true, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'taxable' => 'price_shipping',
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'amount' => 9.09, 'included' => true, 'description' => '10%'],
                ['type' => 'tax', 'amount' => 9.09, 'included' => true, 'description' => '10%'],
            ],
            'orderTotalPrice' => 200,
            'orderTotalQty' => 3,
            'orderTotalTax' => 0,
            'orderTotalTaxIncluded' => 18.18,
        ],
    ],

    'tax-zone-mismatch-1' => [
        ['countryCode' => 'AU'],
        [['price' => 100, 'qty' => 1]],
        [[
            'name' => 'Australia', 'code' => 'GST', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => false, 'taxable' => 'order_total_price', 'zoneCountries' => ['NL'],
        ]],
        [
            'adjustments' => [],
            'orderTotalPrice' => 100,
            'orderTotalQty' => 1,
            'orderTotalTax' => 0,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-zone-mismatch-2' => [
        ['countryCode' => 'AU'],
        [['price' => 100, 'qty' => 1]],
        [[
            'name' => 'Netherlands', 'code' => 'NLVAT', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => true, 'removeIncluded' => true, 'taxable' => 'order_total_price',
            'zoneCountries' => ['NL'], // does not match AU on purpose, to create a mismatch
        ]],
        [
            'adjustments' => [
                ['type' => 'discount', 'amount' => -9.09, 'included' => false, 'description' => '10%'],
            ],
            'orderTotalPrice' => 90.91,
            'orderTotalQty' => 1,
            'orderTotalTax' => 0,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-valid-vat-1' => [
        ['countryCode' => 'CZ', 'organizationTaxId' => 'CZ25666011', '_validateVat' => true],
        [['price' => 100, 'qty' => 1]],
        [[
            'name' => 'CZ Vat', 'code' => 'CZVAT', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => true, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'removeVatIncluded' => true, 'taxable' => 'order_total_price', 'zoneCountries' => ['CZ'],
        ]],
        [
            'adjustments' => [
                ['type' => 'discount', 'amount' => -9.09, 'included' => false, 'description' => '10%'],
            ],
            'orderTotalPrice' => 90.91,
            'orderTotalQty' => 1,
            'orderTotalTax' => 0,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-valid-vat-2 (included tax that does not apply since it has a valid tax ID, but does not remove)' => [
        ['countryCode' => 'CZ', 'organizationTaxId' => 'CZ25666011', '_validateVat' => true],
        [['price' => 100, 'qty' => 1]],
        [[
            'name' => 'CZ Vat', 'code' => 'CZVAT', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => true, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'removeVatIncluded' => false, 'taxable' => 'order_total_price', 'zoneCountries' => ['CZ'],
        ]],
        [
            'adjustments' => [],
            'orderTotalPrice' => 100,
            'orderTotalQty' => 1,
            'orderTotalTax' => 0,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-invalid-vat-1 (does not get removed due to an invalid VAT ID)' => [
        ['countryCode' => 'CZ', 'organizationTaxId' => 'CZ99999999', '_validateVat' => false],
        [['price' => 100, 'qty' => 1]],
        [[
            'name' => 'CZ Vat', 'code' => 'CZVAT', 'taxCategoryId' => 1, 'rate' => 0.1,
            'include' => true, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'removeVatIncluded' => true, 'taxable' => 'order_total_price', 'zoneCountries' => ['CZ'],
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'description' => '10%', 'included' => true, 'amount' => 9.09],
            ],
            'orderTotalPrice' => 100,
            'orderTotalQty' => 1,
            'orderTotalTax' => 0,
            'orderTotalTaxIncluded' => 9.09,
        ],
    ],

    'tax-20pct-vat-not-included-taxable-line-item-price' => [
        ['countryCode' => 'UK'],
        [['price' => 49.17, 'qty' => 1]],
        [[
            'name' => 'UK', 'code' => 'VAT', 'taxCategoryId' => 1, 'rate' => 0.2,
            'include' => false, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'taxable' => 'price',
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'amount' => 9.83, 'included' => false, 'description' => '20%'],
            ],
            'orderTotalPrice' => 59,
            'orderTotalQty' => 1,
            'orderTotalTax' => 9.83,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-20pct-vat-not-included-taxable-purchasable-price' => [
        ['countryCode' => 'UK'],
        [['price' => 49.17, 'qty' => 1]],
        [[
            'name' => 'UK', 'code' => 'VAT', 'taxCategoryId' => 1, 'rate' => 0.2,
            'include' => false, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'taxable' => 'purchasable',
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'amount' => 9.83, 'included' => false, 'description' => '20%'],
            ],
            'orderTotalPrice' => 59,
            'orderTotalQty' => 1,
            'orderTotalTax' => 9.83,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-20pct-vat-not-included-taxable-line-item-price-qty-4' => [
        ['countryCode' => 'UK'],
        [['price' => 49.17, 'qty' => 4]],
        [[
            'name' => 'UK', 'code' => 'VAT', 'taxCategoryId' => 1, 'rate' => 0.2,
            'include' => false, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'taxable' => 'price',
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'amount' => 39.34, 'included' => false, 'description' => '20%'],
            ],
            'orderTotalPrice' => 236.02,
            'orderTotalQty' => 4,
            'orderTotalTax' => 39.34,
            'orderTotalTaxIncluded' => 0,
        ],
    ],

    'tax-20pct-vat-not-included-taxable-purchasable-price-qty-4' => [
        ['countryCode' => 'UK'],
        [['price' => 49.17, 'qty' => 4]],
        [[
            'name' => 'UK', 'code' => 'VAT', 'taxCategoryId' => 1, 'rate' => 0.2,
            'include' => false, 'taxIdValidators' => ['craft\\commerce\\taxidvalidators\\EuVatIdValidator'],
            'taxable' => 'purchasable',
        ]],
        [
            'adjustments' => [
                ['type' => 'tax', 'amount' => 39.32, 'included' => false, 'description' => '20%'],
            ],
            'orderTotalPrice' => 236,
            'orderTotalQty' => 4,
            'orderTotalTax' => 39.32,
            'orderTotalTaxIncluded' => 0,
        ],
    ],
]);
