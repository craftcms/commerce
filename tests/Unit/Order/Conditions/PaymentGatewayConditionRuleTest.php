<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Conditions\PaymentGatewayConditionRule;

/**
 * Older saved discount/shipping-rule configs may still have a single `value` key instead of the
 * `values` array this rule (a BaseMultiSelectConditionRule) actually stores state in. Both
 * directions of that backwards-compatibility conversion are exercised here.
 */
test('setAttributes() converts a legacy single value into the values array', function() {
    $rule = new PaymentGatewayConditionRule();
    $rule->setAttributes(['value' => 'gateway-uid-1']);

    expect($rule->getValues())->toBe(['gateway-uid-1']);
});

test('setAttributes() prefers values over a legacy value when both are present', function() {
    $rule = new PaymentGatewayConditionRule();
    $rule->setAttributes(['value' => 'gateway-uid-1', 'values' => ['gateway-uid-2']]);

    expect($rule->getValues())->toBe(['gateway-uid-2']);
});

test('getConfig() never emits the legacy value key', function() {
    $rule = new PaymentGatewayConditionRule();
    $rule->setValues(['gateway-uid-1', 'gateway-uid-2']);

    $config = $rule->getConfig();

    expect($config)->not->toHaveKey('value');
    expect($config['values'])->toBe(['gateway-uid-1', 'gateway-uid-2']);
});

test('getValue() returns the first of multiple selected values', function() {
    $rule = new PaymentGatewayConditionRule();
    $rule->setValues(['gateway-uid-1', 'gateway-uid-2']);

    expect($rule->getValue())->toBe('gateway-uid-1');
});

test('setValue() replaces the values array with a single-item array', function() {
    $rule = new PaymentGatewayConditionRule();
    $rule->setValues(['gateway-uid-1', 'gateway-uid-2']);
    $rule->setValue('gateway-uid-3');

    expect($rule->getValues())->toBe(['gateway-uid-3']);
});
