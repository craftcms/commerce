<?php

declare(strict_types=1);

use CraftCms\Commerce\Address\Conditions\PostalCodeFormulaConditionRule;
use CraftCms\Commerce\Customer\Conditions\SignedInConditionRule;
use CraftCms\Commerce\Order\Conditions\CompletedConditionRule;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Store\Stores;

test('getPercentDiscountAsPercent formats the (always negative) percent discount as a percent', function(string|int|float $percentDiscount, string $expected) {
    $discount = new Discount();
    $discount->percentDiscount = (float)$percentDiscount;

    expect($discount->getPercentDiscountAsPercent())->toBe($expected);
})->with([
    ['-0.1000', '10%'],
    [0, '0%'],
    [-0.1, '10%'],
    [-0.15, '15%'],
    [-0.105, '10.5%'],
    [-0.10504, '10.504%'],
    ['-0.1050400', '10.504%'],
]);

test('hasOrderCondition is false when no order condition has been set', function() {
    expect((new Discount())->hasOrderCondition())->toBeFalse();
});

test('hasOrderCondition is false when the order condition has no rules', function() {
    $discount = new Discount();
    $discount->setOrderCondition($discount->getOrderCondition());

    expect($discount->hasOrderCondition())->toBeFalse();
});

test('hasOrderCondition is true once a rule is added', function() {
    $discount = new Discount();
    $discount->storeId = app(Stores::class)->getPrimaryStore()->id;
    $condition = $discount->getOrderCondition();
    $condition->addConditionRule(new CompletedConditionRule());
    $discount->setOrderCondition($condition);

    expect($discount->hasOrderCondition())->toBeTrue();
});

test('hasCustomerCondition is false when no customer condition has been set', function() {
    expect((new Discount())->hasCustomerCondition())->toBeFalse();
});

test('hasCustomerCondition is false when the customer condition has no rules', function() {
    $discount = new Discount();
    $discount->setCustomerCondition($discount->getCustomerCondition());

    expect($discount->hasCustomerCondition())->toBeFalse();
});

test('hasCustomerCondition is true once a rule is added', function() {
    $discount = new Discount();
    $condition = $discount->getCustomerCondition();
    $condition->addConditionRule(new SignedInConditionRule());
    $discount->setCustomerCondition($condition);

    expect($discount->hasCustomerCondition())->toBeTrue();
});

test('hasBillingAddressCondition is false when no billing address condition has been set', function() {
    expect((new Discount())->hasBillingAddressCondition())->toBeFalse();
});

test('hasBillingAddressCondition is false when the billing address condition has no rules', function() {
    $discount = new Discount();
    $discount->setBillingAddressCondition($discount->getBillingAddressCondition());

    expect($discount->hasBillingAddressCondition())->toBeFalse();
});

test('hasBillingAddressCondition is true once a rule is added', function() {
    $discount = new Discount();
    $condition = $discount->getBillingAddressCondition();
    $condition->addConditionRule(new PostalCodeFormulaConditionRule());
    $discount->setBillingAddressCondition($condition);

    expect($discount->hasBillingAddressCondition())->toBeTrue();
});

test('hasShippingAddressCondition is false when no shipping address condition has been set', function() {
    expect((new Discount())->hasShippingAddressCondition())->toBeFalse();
});

test('hasShippingAddressCondition is false when the shipping address condition has no rules', function() {
    $discount = new Discount();
    $discount->setShippingAddressCondition($discount->getShippingAddressCondition());

    expect($discount->hasShippingAddressCondition())->toBeFalse();
});

test('hasShippingAddressCondition is true once a rule is added', function() {
    $discount = new Discount();
    $condition = $discount->getShippingAddressCondition();
    $condition->addConditionRule(new PostalCodeFormulaConditionRule());
    $discount->setShippingAddressCondition($condition);

    expect($discount->hasShippingAddressCondition())->toBeTrue();
});
