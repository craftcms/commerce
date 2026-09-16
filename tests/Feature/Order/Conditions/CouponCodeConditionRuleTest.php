<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Order\Conditions\CouponCodeConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

function couponCondition(?string $value, string $operator = '='): OrderCondition
{
    $condition = Order::createCondition();
    $rule = new CouponCodeConditionRule();
    $rule->value = $value;
    $rule->operator = $operator;
    $condition->addConditionRule($rule);

    return $condition;
}

test('matchElement matches coupon codes', function(?string $ruleValue, string $operator, ?string $orderCoupon, bool $expectedMatch) {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $order->couponCode = $orderCoupon;

    $condition = couponCondition($ruleValue, $operator);

    expect($condition->matchElement($order))->toBe($expectedMatch);
})->with([
    'match-equals' => ['coupon1', '=', 'coupon1', true],
    'match-equals-case-insensitive' => ['coupon1', '=', 'cOuPoN1', true],
    'no-match-equals' => ['coupon1', '=', 'coupon2', false],
    'no-match-equals-case-insensitive' => ['coupon1', '=', 'cOuPoN2', false],
    'no-match-equals-null' => ['coupon1', '=', null, false],
    'match-contains' => ['coupon1', '**', 'coupon1', true],
    'match-contains-case-insensitive' => ['coupon1', '**', 'cOuPoN1', true],
    'no-match-contains' => ['coupon1', '**', 'coupon2', false],
    'no-match-contains-case-insensitive' => ['coupon1', '**', 'cOuPoN2', false],
    'match-begins-with' => ['coupon', 'bw', 'coupon1', true],
    'match-begins-with-case-insensitive' => ['coupon', 'bw', 'cOuPoN1', true],
    'no-match-begins-with' => ['coupon', 'bw', 'foocoupon2', false],
    'no-match-begins-with-case-insensitive' => ['coupon', 'bw', 'foocOuPoN2', false],
    'match-ends-with' => ['pon1', 'ew', 'coupon1', true],
    'match-ends-with-case-insensitive' => ['pon1', 'ew', 'cOuPoN1', true],
    'no-match-ends-with' => ['pon2', 'ew', 'coupon2foo', false],
    'no-match-ends-with-case-insensitive' => ['pon2', 'ew', 'cOuPoN2foo', false],
]);

test('modifyQuery filters orders by coupon code', function(?string $ruleValue, string $operator, ?string $orderCoupon, bool $expectedMatch) {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $order->couponCode = $orderCoupon;
    if (!Elements::saveElement($order, false)) {
        throw new RuntimeException('Could not save order: ' . json_encode($order->errors()->all()));
    }

    $condition = couponCondition($ruleValue, $operator);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    if ($expectedMatch) {
        expect($ids)->toContain($order->id);
    } else {
        expect($ids)->not->toContain($order->id);
    }
})->with([
    'match-equals' => ['coupon1', '=', 'coupon1', true],
    'match-equals-case-insensitive' => ['coupon1', '=', 'cOuPoN1', true],
    'no-match-equals' => ['coupon1', '=', 'coupon2', false],
    'match-contains' => ['coupon1', '**', 'coupon1', true],
    'match-begins-with' => ['coupon', 'bw', 'coupon1', true],
    'no-match-begins-with' => ['coupon', 'bw', 'foocoupon2', false],
    'match-ends-with' => ['pon1', 'ew', 'coupon1', true],
    'no-match-ends-with' => ['pon2', 'ew', 'coupon2foo', false],
]);
