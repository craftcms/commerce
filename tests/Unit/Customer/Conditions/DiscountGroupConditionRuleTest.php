<?php

declare(strict_types=1);

use CraftCms\Commerce\Customer\Conditions\DiscountGroupConditionRule;

/**
 * `DiscountGroupConditionRule` adds a custom "is in all of" operator on top of the base group
 * rule's "is one of"/"is not one of", requiring the matched value to contain every configured
 * group, not just one — tested directly via reflection since `matchValue()` is protected and
 * this logic doesn't need a real User/UserGroup/DB.
 */
$match = fn(DiscountGroupConditionRule $rule, array|string|null $value): bool => new ReflectionMethod($rule, 'matchValue')->invoke($rule, $value);

test('no configured groups always matches', function() use ($match) {
    $rule = new DiscountGroupConditionRule();
    $rule->operator = 'in';

    expect($match($rule, ['group-a']))->toBeTrue();
    expect($match($rule, null))->toBeTrue();
});

test('in-all operator requires every configured group to be present', function() use ($match) {
    $rule = new DiscountGroupConditionRule();
    $rule->operator = 'inAll';
    $rule->setValues(['group-a', 'group-b']);

    expect($match($rule, ['group-a', 'group-b']))->toBeTrue();
    expect($match($rule, ['group-a', 'group-b', 'group-c']))->toBeTrue();
    expect($match($rule, ['group-a']))->toBeFalse();
    expect($match($rule, []))->toBeFalse();
});

test('in operator matches when any configured group is present', function() use ($match) {
    $rule = new DiscountGroupConditionRule();
    $rule->operator = 'in';
    $rule->setValues(['group-a', 'group-b']);

    expect($match($rule, ['group-a']))->toBeTrue();
    expect($match($rule, ['group-c']))->toBeFalse();
});

test('not-in operator matches when no configured group is present', function() use ($match) {
    $rule = new DiscountGroupConditionRule();
    $rule->operator = 'ni';
    $rule->setValues(['group-a', 'group-b']);

    expect($match($rule, ['group-c']))->toBeTrue();
    expect($match($rule, ['group-a']))->toBeFalse();
});
