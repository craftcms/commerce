<?php

declare(strict_types=1);

use CraftCms\Cms\Condition\Contracts\ConditionRuleInterface;
use CraftCms\Commerce\Order\Conditions\CompletedConditionRule;
use CraftCms\Commerce\Order\Conditions\ContainsPurchasablesConditionRule;
use CraftCms\Commerce\Order\Conditions\HasAdminNoticesConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Conditions\OrderStatusConditionRule;
use CraftCms\Commerce\Order\Conditions\PaidConditionRule;
use CraftCms\Commerce\Order\Conditions\ReferenceConditionRule;
use CraftCms\Commerce\Order\Conditions\ShippingMethodConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalPriceConditionRule;
use CraftCms\Commerce\Order\Conditions\TotalQtyConditionRule;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

/**
 * Every rule exercised here is one of the rules that were silently non-functional through
 * modifyQuery() until CraftCms\Commerce\Order\Queries\Concerns\QueriesOrderAttributes was
 * introduced — none had a modifyQuery()-exercising test before, only matchElement() coverage
 * (which doesn't touch the query at all, so never caught the bug).
 */
function conditionWithRule(ConditionRuleInterface $rule): OrderCondition
{
    $condition = Order::createCondition();
    $condition->addConditionRule($rule);

    return $condition;
}

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
});

test('CompletedConditionRule filters by isCompleted', function() {
    $rule = new CompletedConditionRule();
    $rule->value = true;
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->orders['completed-new']->id);
});

test('OrderStatusConditionRule filters by order status', function() {
    $rule = new OrderStatusConditionRule();
    $rule->setValues([app(OrderStatuses::class)->getOrderStatusById($this->fixture->shippedOrderStatusId)->uid]);
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->orders['completed-shipped']->id);
    expect($ids)->not->toContain($this->fixture->orders['completed-new']->id);
});

test('TotalQtyConditionRule (a Values-attribute rule) filters by total quantity', function() {
    $rule = new TotalQtyConditionRule();
    $rule->value = '5';
    $rule->operator = '>=';
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    // completed-new/completed-new-past have 1 white + 4 blue = qty 5; completed-shipped has qty 1.
    expect($ids)->toContain($this->fixture->orders['completed-new']->id);
    expect($ids)->not->toContain($this->fixture->orders['completed-shipped']->id);
});

test('TotalPriceConditionRule (a Currency-attribute rule) filters by total price', function() {
    $rule = new TotalPriceConditionRule();
    $rule->value = '50';
    $rule->operator = '>';
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    // completed-new totals ~107.95 (1 white @ 19.99 + 4 blue @ 21.99); completed-shipped is ~19.99.
    expect($ids)->toContain($this->fixture->orders['completed-new']->id);
    expect($ids)->not->toContain($this->fixture->orders['completed-shipped']->id);
});

test('ReferenceConditionRule (a Text-attribute rule) filters by reference', function() {
    $order = Order::find()->id($this->fixture->orders['completed-new']->id)->one();
    $rule = new ReferenceConditionRule();
    $rule->value = $order->reference;
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($order->id);
    expect($ids)->not->toContain($this->fixture->orders['completed-shipped']->id);
});

test('PaidConditionRule filters unpaid orders', function() {
    $rule = new PaidConditionRule();
    $rule->value = false;
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    // None of the fixture orders have any payments recorded.
    expect($ids)->toContain($this->fixture->orders['completed-new']->id);
});

test('ShippingMethodConditionRule filters by shipping method handle', function() {
    $rule = new ShippingMethodConditionRule();
    $rule->setValues(['usShipping']);
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->orders['completed-new']->id);
    expect($ids)->not->toContain($this->fixture->orders['completed-shipped']->id);
});

test('HasAdminNoticesConditionRule filters orders without admin notices', function() {
    $rule = new HasAdminNoticesConditionRule();
    $rule->value = false;
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    expect($ids)->toContain($this->fixture->orders['completed-new']->id);
});

test('ContainsPurchasablesConditionRule filters orders containing a specific purchasable', function() {
    $rule = new ContainsPurchasablesConditionRule();
    $rule->setElementIds([$this->fixture->blue->id]);
    $condition = conditionWithRule($rule);

    $query = Order::find();
    $condition->modifyQuery($query);
    $ids = $query->ids();

    // Only completed-new/completed-new-past include the blue variant; completed-shipped is white-only.
    expect($ids)->toContain($this->fixture->orders['completed-new']->id);
    expect($ids)->not->toContain($this->fixture->orders['completed-shipped']->id);
});
