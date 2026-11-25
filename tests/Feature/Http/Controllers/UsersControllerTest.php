<?php

declare(strict_types=1);

use CraftCms\Cms\Element\EmbeddedElementIndex;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('embeds element indexes for the user’s orders, active carts, and inactive carts', function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $fixture = OrdersFixture::seed();
    $carts = [];
    foreach (['active', 'inactive'] as $key) {
        $cart = new Order();
        $cart->number = app(Carts::class)->generateCartNumber();
        $cart->setCustomer($fixture->customer);
        Elements::saveElement($cart, false);
        $carts[$key] = $cart->id;
    }
    DB::table(Table::ORDERS)->where('id', $carts['inactive'])->update(['dateUpdated' => now()->subYear()]);
    DB::table('elements')->where('id', $carts['inactive'])->update(['dateUpdated' => now()->subYear()]);

    $html = get(Url::cpUrl("users/{$fixture->customer->id}/commerce"))->assertOk()->getContent();

    preg_match_all('/"token":"([^"]+)"/', $html, $matches);
    $listedIds = collect($matches[1])->map(function(string $token) {
        $index = EmbeddedElementIndex::fromToken(stripslashes($token));
        expect($index?->elementType())->toBe(Order::class);

        $query = Order::find();
        foreach ($index->getCriteria() as $param => $value) {
            $query->$param($value);
        }

        return $query->collectIds()->sort()->values()->all();
    })->all();

    expect($listedIds)->toBe([
        collect($fixture->orders)->pluck('id')->sort()->values()->all(),
        [$carts['active']],
        [$carts['inactive']],
    ]);
});
