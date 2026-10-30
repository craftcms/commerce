<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Data\OrderNotice;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Enums\OrderNoticeType;

beforeEach(function() {
    $this->order = new Order();
});

test('addNotice and addNotices append to getNotices, and getFirstNotice returns the earliest match', function() {
    $firstNotice = new OrderNotice([
        'type' => 'priceChange',
        'attribute' => 'lineItems',
        'message' => 'The Price of the product changed.',
    ]);
    $this->order->addNotice($firstNotice);

    $notices = $this->order->getNotices();
    $firstFromOrder = $this->order->getFirstNotice();
    expect($firstFromOrder->type)->toBe($firstNotice->type);
    expect($firstFromOrder->attribute)->toBe($firstNotice->attribute);
    expect($firstFromOrder->message)->toBe($firstNotice->message);
    expect($notices)->toHaveCount(1);

    $secondNotice = new OrderNotice([
        'type' => 'lineItemRemoved',
        'attribute' => 'lineItems',
        'message' => 'The x Product is no longer available and has been removed.',
    ]);
    $this->order->addNotice($secondNotice);

    // The earlier `getNotices()` call returned a snapshot, so it doesn't grow after the fact.
    expect($notices)->toHaveCount(1);
    expect($this->order->getNotices())->toHaveCount(2);

    $this->order->addNotices([$firstNotice, $secondNotice]);
    expect($this->order->getNotices())->toHaveCount(4);
});

test('clearNotices removes matches by type, by attribute, by both, or all customer notices', function() {
    $firstNotice = new OrderNotice([
        'type' => 'priceChange',
        'attribute' => 'lineItems',
        'message' => 'The Price of the product changed.',
    ]);
    $secondNotice = new OrderNotice([
        'type' => 'lineItemRemoved',
        'attribute' => 'lineItems',
        'message' => 'The x Product is no longer available and has been removed.',
    ]);

    $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice]);
    expect($this->order->getNotices())->toHaveCount(4);

    // Clearing by type
    $this->order->clearNotices('lineItemRemoved');
    expect($this->order->getNotices())->toHaveCount(2);
    $this->order->clearNotices('priceChange');
    expect($this->order->getNotices())->toHaveCount(0);

    $thirdNotice = new OrderNotice([
        'type' => 'couponNotValid',
        'attribute' => 'couponCode',
        'message' => 'The x Product is no longer available and has been removed.',
    ]);

    // Clearing by attribute
    $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice, $thirdNotice]);
    expect($this->order->getNotices())->toHaveCount(5);
    $this->order->clearNotices(null, 'lineItems');
    expect($this->order->getNotices())->toHaveCount(1); // only $thirdNotice remains

    // Clearing all
    $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice, $thirdNotice]);
    $this->order->clearNotices();
    expect($this->order->getNotices())->toHaveCount(0);

    // Clearing by both type and attribute
    $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice, $thirdNotice]);
    $this->order->clearNotices('lineItemRemoved', 'lineItems');
    expect($this->order->getNotices())->toHaveCount(3); // both $priceChange copies and $thirdNotice remain

    expect($this->order->hasNotices())->toBeTrue();
    expect($this->order->hasNotices('couponNotValid'))->toBeTrue();
    expect($this->order->getNotices('couponNotValid'))->toHaveCount(1);
    expect($this->order->hasNotices(null, 'lineItems'))->toBeTrue();
    expect($this->order->getNotices(null, 'lineItems'))->toHaveCount(2);
});

test('getNotices excludes admin notices by default', function() {
    $adminNotice = new OrderNotice([
        'type' => 'adminAlert',
        'attribute' => 'order',
        'message' => 'This order needs review.',
        'noticeType' => OrderNoticeType::Admin,
    ]);
    $customerNotice = new OrderNotice([
        'type' => 'priceChange',
        'attribute' => 'lineItems',
        'message' => 'A price changed.',
    ]);

    $this->order->addNotices([$adminNotice, $customerNotice]);

    expect($this->order->getNotices())->toHaveCount(1);
    expect($this->order->getNotices()[0]->type)->toBe('priceChange');
    expect($this->order->hasNotices('adminAlert'))->toBeFalse();
});

test('getAdminNotices and hasAdminNotices report only admin notices', function() {
    $adminNotice = new OrderNotice([
        'type' => 'adminAlert',
        'attribute' => 'order',
        'message' => 'This order needs review.',
        'noticeType' => OrderNoticeType::Admin,
    ]);
    $customerNotice = new OrderNotice([
        'type' => 'priceChange',
        'attribute' => 'lineItems',
        'message' => 'A price changed.',
    ]);

    $this->order->addNotices([$adminNotice, $customerNotice]);

    expect($this->order->getAdminNotices())->toHaveCount(1);
    expect($this->order->getAdminNotices()[0]->type)->toBe('adminAlert');
    expect($this->order->hasAdminNotices())->toBeTrue();
});

test('clearNotices preserves admin notices unless the admin type is explicitly included', function() {
    $adminNotice = new OrderNotice([
        'type' => 'adminAlert',
        'attribute' => 'order',
        'message' => 'This order needs review.',
        'noticeType' => OrderNoticeType::Admin,
    ]);
    $customerNotice = new OrderNotice([
        'type' => 'priceChange',
        'attribute' => 'lineItems',
        'message' => 'A price changed.',
    ]);

    $this->order->addNotices([$adminNotice, $customerNotice]);

    // Clearing without an explicit notice type only clears customer notices.
    $this->order->clearNotices();

    expect($this->order->getNotices())->toHaveCount(0);
    expect($this->order->getAdminNotices())->toHaveCount(1);
    expect($this->order->hasAdminNotices())->toBeTrue();

    $this->order->clearNotices(noticeTypes: [OrderNoticeType::Customer, OrderNoticeType::Admin]);

    expect($this->order->getNotices())->toHaveCount(0);
    expect($this->order->getAdminNotices())->toHaveCount(0);
    expect($this->order->hasAdminNotices())->toBeFalse();
});
