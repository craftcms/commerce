<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Adjuster\Discount;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

beforeEach(function() {
    $this->order = new Order();
});

test('getTotalPrice sums line item subtotals (net of promotional prices) plus non-included adjustments', function() {
    $lineItem1 = new LineItem();
    $lineItem1->qty = 2;
    $lineItem1->price = 10;
    expect($lineItem1->getSubtotal())->toBe(20.0);

    $lineItem2 = new LineItem();
    $lineItem2->qty = 3;
    $lineItem2->price = 20;
    expect($lineItem2->getSubtotal())->toBe(60.0);

    $this->order->setLineItems([$lineItem1, $lineItem2]);
    expect($this->order->getTotalPrice())->toBe(80.0);

    $lineItem2->promotionalPrice = 15;
    $this->order->setLineItems([$lineItem1, $lineItem2]);
    expect($this->order->getTotalPrice())->toBe(65.0);

    // Reset line item 2's promotional price
    $lineItem2->promotionalPrice = null;

    $adjustment1 = new OrderAdjustment();
    $adjustment1->amount = -10;
    $adjustment1->type = Discount::ADJUSTMENT_TYPE;
    $adjustment1->setLineItem($lineItem1);
    $adjustment1->name = 'Discount';
    $adjustment1->description = '10 bucks off';
    $adjustment1->setOrder($this->order);
    $this->order->setAdjustments([$adjustment1]);

    expect($this->order->getTotalPrice())->toBe(70.0);

    $adjustment2 = new OrderAdjustment();
    $adjustment2->amount = -5;
    $adjustment2->type = Discount::ADJUSTMENT_TYPE;
    $adjustment2->setLineItem($lineItem2);
    $adjustment2->name = 'Discount';
    $adjustment2->description = '5 bucks off';
    $adjustment2->setOrder($this->order);

    $this->order->setAdjustments([$adjustment1, $adjustment2]);
    expect($this->order->getTotalPrice())->toBe(65.0);

    // An included adjustment (e.g. tax already baked into the price) doesn't change the total.
    $adjustment3 = new OrderAdjustment();
    $adjustment3->amount = 5;
    $adjustment3->setLineItem($lineItem2);
    $adjustment3->name = 'Tax';
    $adjustment3->description = '5 buck tax';
    $adjustment3->included = true;
    $adjustment3->setOrder($this->order);

    $this->order->setAdjustments([$adjustment1, $adjustment2, $adjustment3]);
    expect($this->order->getTotalPrice())->toBe(65.0);
});
