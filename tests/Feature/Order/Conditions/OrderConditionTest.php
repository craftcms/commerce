<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Conditions\CompletedConditionRule;
use CraftCms\Commerce\Order\Conditions\CouponCodeConditionRule;
use CraftCms\Commerce\Order\Conditions\CustomerConditionRule;
use CraftCms\Commerce\Order\Conditions\DateOrderedConditionRule;
use CraftCms\Commerce\Order\Conditions\HasPurchasableConditionRule;
use CraftCms\Commerce\Order\Conditions\ItemSubtotalConditionRule;
use CraftCms\Commerce\Order\Conditions\ItemTotalConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Conditions\OrderSiteConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderStatusConditionRule;
use CraftCms\Commerce\Order\Conditions\PaidConditionRule;
use CraftCms\Commerce\Order\Conditions\ReferenceConditionRule;
use CraftCms\Commerce\Order\Conditions\ShippingMethodConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalDiscountConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalPaidConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalQtyConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalTaxConditionRule;
use CraftCms\Commerce\Order\Elements\Order;

test('createCondition returns an OrderCondition', function() {
    expect(Order::createCondition())->toBeInstanceOf(OrderCondition::class);
});

test('the condition exposes all built-in order condition rule types', function() {
    $rules = array_keys(Order::createCondition()->getSelectableConditionRules());

    expect($rules)->toContain(
        DateOrderedConditionRule::class,
        CompletedConditionRule::class,
        CouponCodeConditionRule::class,
        CustomerConditionRule::class,
        PaidConditionRule::class,
        HasPurchasableConditionRule::class,
        ItemSubtotalConditionRule::class,
        ItemTotalConditionRule::class,
        OrderStatusConditionRule::class,
        OrderSiteConditionRule::class,
        ReferenceConditionRule::class,
        ShippingMethodConditionRule::class,
        TotalDiscountConditionRule::class,
        TotalPaidConditionRule::class,
        TotalPriceConditionRule::class,
        TotalQtyConditionRule::class,
        TotalTaxConditionRule::class,
        TotalConditionRule::class,
    );
});

/**
 * `$queryParams` restricts the builder to rules that don't compete with a param the condition's
 * caller already controls outside the builder (see `HasOrdersConditionRule`, which sets
 * `queryParams = ['customerId']` since it's already scoping the nested order query to a single
 * customer via `Order::find()->customerId(...)`).
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
