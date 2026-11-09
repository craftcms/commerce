<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    $this->user = User::find()->admin(true)->one();
    actingAs($this->user);
    prioritizeCommerceRoutes();

    $this->path = '/' . Cms::config()->cpTrigger . '/users/' . $this->user->id . '/commerce';
});

it('lists the customer’s orders and carts as read-only indexes', function() {
    get($this->path)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::users/Commerce', false)
            ->where('elementType', Order::class)
            ->has('sections', 3)
            ->where('sections.0.key', 'completed-orders')
            ->where('sections.0.criteria.customerId', $this->user->id)
            ->where('sections.0.criteria.isCompleted', true)
            ->where('sections.1.criteria.isCompleted', false)
            ->where('sections.2.criteria.isCompleted', false));
});
