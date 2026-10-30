<?php

declare(strict_types=1);

use CraftCms\Commerce\Customer\Conditions\HasOrdersConditionRule;
use CraftCms\Commerce\Order\Conditions\CompletedConditionRule;
use CraftCms\Commerce\Order\Conditions\CustomerConditionRule;

/**
 * The nested `OrderCondition` this rule builds is already scoped to a single customer
 * externally (`Order::find()->customerId($element->id)` in `matchElement()`), so its builder
 * shouldn't also offer a redundant "Customer" rule — `queryParams = ['customerId']` excludes it.
 */
test('the nested order condition excludes the customer rule', function() {
    $rule = new HasOrdersConditionRule();

    $selectable = $rule->getOrderCondition()->getSelectableConditionRules();

    expect($selectable)->not->toHaveKey(CustomerConditionRule::class);
    expect($selectable)->toHaveKey(CompletedConditionRule::class);
});
