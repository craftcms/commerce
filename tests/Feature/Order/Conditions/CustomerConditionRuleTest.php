<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Conditions\CustomerConditionRule;
use CraftCms\Commerce\Order\Conditions\OrderCondition;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

function customerCondition(array $values, string $operator = 'in'): OrderCondition
{
    $condition = Order::createCondition();
    $rule = new CustomerConditionRule();
    $rule->values = $values;
    $rule->operator = $operator;
    $condition->addConditionRule($rule);

    return $condition;
}

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
    $this->order = $this->fixture->orders['completed-new'];

    $this->otherUser = new User();
    $this->otherUser->username = 'not-customer1';
    $this->otherUser->email = 'not-customer1@crafttest.com';
    $this->otherUser->active = true;
    if (!Elements::saveElement($this->otherUser)) {
        throw new RuntimeException('Could not save user: ' . json_encode($this->otherUser->errors()->all()));
    }
});

test('matchElement (in) matches the order\'s customer', function() {
    $condition = customerCondition([$this->fixture->customer->id]);

    expect($condition->matchElement($this->order))->toBeTrue();
});

test('matchElement (in) does not match a different customer', function() {
    $condition = customerCondition([$this->otherUser->id]);

    expect($condition->matchElement($this->order))->toBeFalse();
});

test('matchElement (not in) matches when the order\'s customer is excluded', function() {
    $condition = customerCondition([$this->otherUser->id], 'ni');

    expect($condition->matchElement($this->order))->toBeTrue();
});

test('matchElement (not in) does not match when the order\'s customer is the excluded one', function() {
    $condition = customerCondition([$this->fixture->customer->id], 'ni');

    expect($condition->matchElement($this->order))->toBeFalse();
});

test('modifyQuery (in) matches the order\'s customer', function() {
    $condition = customerCondition([$this->fixture->customer->id]);

    $query = Order::find();
    $condition->modifyQuery($query);

    expect($query->ids())->toContain($this->order->id);
});

test('modifyQuery (in) does not match a different customer', function() {
    $condition = customerCondition([$this->otherUser->id]);

    $query = Order::find();
    $condition->modifyQuery($query);

    expect($query->ids())->toBeEmpty();
});

test('modifyQuery (not in) matches when the order\'s customer is excluded', function() {
    $condition = customerCondition([$this->otherUser->id], 'ni');

    $query = Order::find();
    $condition->modifyQuery($query);

    expect($query->ids())->toContain($this->order->id);
});

test('modifyQuery (not in) does not match when the order\'s customer is the excluded one', function() {
    $condition = customerCondition([$this->fixture->customer->id], 'ni');

    $query = Order::find();
    $condition->modifyQuery($query);

    expect($query->ids())->not->toContain($this->order->id);
});
