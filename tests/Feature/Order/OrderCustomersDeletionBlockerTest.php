<?php

declare(strict_types=1);

use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\DeletionBlockers\OrderCustomersDeletionBlocker;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

it('lists the customer\'s completed orders as chips in its details', function() {
    $fixture = OrdersFixture::seed();

    $blocker = new OrderCustomersDeletionBlocker(new ElementCollection([$fixture->customer]), false);
    $details = $blocker->getDetails();

    expect($blocker->isActive())->toBeTrue()
        ->and($details)->toContain('edm-elements')
        ->and(substr_count($details, '<li'))->toBe(count($fixture->orders));
});

it('is inactive and has no details for a customer without completed orders', function() {
    $fixture = OrdersFixture::seed();
    $user = new User();
    $user->username = 'no-orders';
    $user->email = 'no-orders@crafttest.com';
    Elements::saveElement($user);

    $blocker = new OrderCustomersDeletionBlocker(new ElementCollection([$user]), false);

    expect($blocker->isActive())->toBeFalse()
        ->and($blocker->getDetails())->toBeNull();
});
