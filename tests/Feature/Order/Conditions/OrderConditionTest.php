<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Conditions\CompletedConditionRule;
use CraftCms\Commerce\Order\Conditions\CustomerConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;

/**
 * `$queryParams` reproduces the exclusive-query-param scoping the legacy Yii2 condition system
 * provided natively, restricting the builder to rules that don't compete with a param the
 * condition's caller already controls outside the builder (see `HasOrdersConditionRule`, which
 * sets `queryParams = ['customerId']` since it's already scoping the nested order query to a
 * single customer via `Order::find()->customerId(...)`).
 */
test('queryParams excludes rules whose exclusive query param is already reserved', function() {
    $condition = new OrderCondition();
    $condition->queryParams = ['customerId'];

    $selectable = $condition->getSelectableConditionRules();

    expect($selectable)->not->toHaveKey(CustomerConditionRule::class);
    expect($selectable)->toHaveKey(CompletedConditionRule::class);
});

test('an empty queryParams allow-list excludes nothing', function() {
    $condition = new OrderCondition();

    $selectable = $condition->getSelectableConditionRules();

    expect($selectable)->toHaveKey(CustomerConditionRule::class);
});
