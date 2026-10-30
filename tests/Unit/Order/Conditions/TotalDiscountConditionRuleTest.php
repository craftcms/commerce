<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Conditions\TotalDiscountConditionRule;

/**
 * Discounts are stored as negative amounts on the order (e.g. -10.0 for a $10 discount), but the
 * rule's configured `value` is entered as a positive number in the UI. `matchValue()`/`paramValue()`
 * negate the configured value before comparing, so these tests exercise that negation directly
 * (via reflection, since `matchValue()` is protected and doesn't need an `Order`/DB to test).
 */
$match = fn(TotalDiscountConditionRule $rule, mixed $value): bool => new ReflectionMethod($rule, 'matchValue')->invoke($rule, $value);

test('empty configured value always matches', function() use ($match) {
    $rule = new TotalDiscountConditionRule();
    $rule->operator = '=';
    $rule->value = '';

    expect($match($rule, -50.0))->toBeTrue();
});

test('greater-than operator compares against the negated value', function() use ($match) {
    $rule = new TotalDiscountConditionRule();
    $rule->operator = '>';
    $rule->value = '5';

    // A $10 discount (-10) is a *bigger* discount than $5 (-5), so -10 > -5 is false —
    // matching the swapped "is less than" label this operator displays for this rule.
    expect($match($rule, -10.0))->toBeFalse();
    // A $2 discount (-2) is smaller than $5, so -2 > -5 is true.
    expect($match($rule, -2.0))->toBeTrue();
});

test('equals operator matches the exact negated amount', function() use ($match) {
    $rule = new TotalDiscountConditionRule();
    $rule->operator = '=';
    $rule->value = '10';

    expect($match($rule, -10.0))->toBeTrue();
    expect($match($rule, -5.0))->toBeFalse();
});
