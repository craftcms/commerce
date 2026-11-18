<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Payment\Gateway\Types\Manual;

use function Pest\Laravel\actingAs;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
    $this->withoutVite();
});

test('a failed save shows the submitted gateway type’s settings again', function() {
    $this->from(Url::cpUrl('commerce/settings/gateways/new'))
        ->post(Url::actionUrl('commerce/gateways/save'), [
            'type' => Manual::class,
            'name' => 'Manual',
            'paymentType' => 'authorize',
            'onlyAllowForZeroPriceOrders' => '1',
        ])
        ->assertRedirect(Url::cpUrl('commerce/settings/gateways/new'));

    // The new gateway screen defaults to the Dummy gateway, which has no settings of its own.
    $this->get(Url::cpUrl('commerce/settings/gateways/new'))
        ->assertOk()
        ->assertSee('onlyAllowForZeroPriceOrders')
        ->assertSee('The handle field is required.');
});
