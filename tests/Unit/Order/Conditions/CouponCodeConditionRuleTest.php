<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Conditions\CouponCodeConditionRule;

/**
 * Coupon codes are matched case-insensitively (unlike the base text rule's EQ/NE operators,
 * which compare exactly) — tested directly via reflection since `matchValue()` is protected
 * and this logic doesn't need an `Order`/DB.
 */
$match = fn(CouponCodeConditionRule $rule, mixed $value): bool => new ReflectionMethod($rule, 'matchValue')->invoke($rule, $value);

test('equals operator is case-insensitive', function() use ($match) {
    $rule = new CouponCodeConditionRule();
    $rule->operator = '=';
    $rule->value = 'SUMMER10';

    expect($match($rule, 'summer10'))->toBeTrue();
    expect($match($rule, 'Summer10'))->toBeTrue();
    expect($match($rule, 'winter10'))->toBeFalse();
});

test('does-not-equal operator is case-insensitive', function() use ($match) {
    $rule = new CouponCodeConditionRule();
    $rule->operator = '!=';
    $rule->value = 'SUMMER10';

    expect($match($rule, 'summer10'))->toBeFalse();
    expect($match($rule, 'winter10'))->toBeTrue();
});

test('empty operator value always matches', function() use ($match) {
    $rule = new CouponCodeConditionRule();
    $rule->operator = '=';
    $rule->value = '';

    expect($match($rule, 'anything'))->toBeTrue();
});

test('empty and not-empty operators check for a coupon code presence', function() use ($match) {
    $emptyRule = new CouponCodeConditionRule();
    $emptyRule->operator = 'empty';

    expect($match($emptyRule, ''))->toBeTrue();
    expect($match($emptyRule, 'summer10'))->toBeFalse();

    $notEmptyRule = new CouponCodeConditionRule();
    $notEmptyRule->operator = 'notempty';

    expect($match($notEmptyRule, 'summer10'))->toBeTrue();
    expect($match($notEmptyRule, ''))->toBeFalse();
});
