<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;

test('saving a user with a changed email syncs the email column on their completed orders and active carts', function() {
    $fixture = OrdersFixture::seed();
    $completedOrder = $fixture->orders['completed-new'];

    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->setCustomer($fixture->customer);
    $lineItem = app(LineItems::class)->create($cart, [
        'purchasableId' => $fixture->white->id,
        'qty' => 4,
        'note' => 'My note',
    ]);
    $cart->setLineItems([$lineItem]);
    if (!Elements::saveElement($cart, false)) {
        throw new RuntimeException('Could not save cart: ' . json_encode($cart->errors()->all()));
    }

    $newEmail = 'changed@emailaddress.xyz';
    $fixture->customer->email = $newEmail;
    if (!Elements::saveElement($fixture->customer, false)) {
        throw new RuntimeException('Could not save customer: ' . json_encode($fixture->customer->errors()->all()));
    }

    $emails = DB::table(Table::ORDERS)
        ->whereIn('id', [$completedOrder->id, $cart->id])
        ->pluck('email');

    expect($emails)->toHaveCount(2);
    foreach ($emails as $email) {
        expect($email)->toBe($newEmail);
    }
});
