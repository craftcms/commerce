<?php

declare(strict_types=1);

use CraftCms\Commerce\Http\Controllers\Settings\OrderStatusesController;
use CraftCms\Commerce\Http\Controllers\StoreManagement\ShippingZonesController;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpKernel\Exception\HttpException;

function callRequireStoreAccess(object $controller, ?int $storeId): void
{
    $method = new ReflectionMethod($controller, 'requireStoreAccess');
    $method->setAccessible(true);
    $method->invoke($controller, $storeId);
}

test('requireStoreAccess allows a store the current user has access to', function() {
    $fixture = OrdersFixture::seed();
    $admin = \CraftCms\Cms\User\Elements\User::find()->admin(true)->one();
    $this->actingAs($admin, 'craft');
    request()->setUserResolver(fn() => $admin);

    $controller = App::make(ShippingZonesController::class);

    callRequireStoreAccess($controller, $fixture->storeId);
})->throwsNoExceptions();

test('requireStoreAccess aborts for a store not in the allowed list', function() {
    $fixture = OrdersFixture::seed();
    $admin = \CraftCms\Cms\User\Elements\User::find()->admin(true)->one();
    $this->actingAs($admin, 'craft');
    request()->setUserResolver(fn() => $admin);

    $controller = App::make(ShippingZonesController::class);

    callRequireStoreAccess($controller, null);
})->throws(HttpException::class);

test('requireStoreAccess only resolves the allowed store list once per controller instance', function() {
    $fixture = OrdersFixture::seed();
    $admin = \CraftCms\Cms\User\Elements\User::find()->admin(true)->one();
    $this->actingAs($admin, 'craft');
    request()->setUserResolver(fn() => $admin);

    $spy = Mockery::spy(Stores::class)->makePartial();
    app()->instance(Stores::class, $spy);

    $controller = App::make(ShippingZonesController::class);

    callRequireStoreAccess($controller, $fixture->storeId);
    callRequireStoreAccess($controller, $fixture->storeId);

    $spy->shouldHaveReceived('getStoresByUserId')->once();
});

test('OrderStatusesController has its own equivalent requireStoreAccess that also memoizes', function() {
    $fixture = OrdersFixture::seed();
    $admin = \CraftCms\Cms\User\Elements\User::find()->admin(true)->one();
    $this->actingAs($admin, 'craft');
    request()->setUserResolver(fn() => $admin);

    $spy = Mockery::spy(Stores::class)->makePartial();
    app()->instance(Stores::class, $spy);

    $controller = App::make(OrderStatusesController::class);

    callRequireStoreAccess($controller, $fixture->storeId);
    callRequireStoreAccess($controller, $fixture->storeId);

    $spy->shouldHaveReceived('getStoresByUserId')->once();
});

test('OrderStatusesController::requireStoreAccess aborts for an invalid store', function() {
    $fixture = OrdersFixture::seed();
    $admin = \CraftCms\Cms\User\Elements\User::find()->admin(true)->one();
    $this->actingAs($admin, 'craft');
    request()->setUserResolver(fn() => $admin);

    $controller = App::make(OrderStatusesController::class);

    callRequireStoreAccess($controller, null);
})->throws(HttpException::class);
