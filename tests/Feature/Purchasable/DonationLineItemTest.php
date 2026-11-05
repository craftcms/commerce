<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\TaxCategories;

use function Pest\Laravel\postJson;

beforeEach(function() {
    prioritizeCommerceRoutes();

    $donation = new Donation();
    $donation->siteId = Sites::getPrimarySite()->id;
    $donation->sku = 'DONATION-CC5';
    $donation->availableForPurchase = true;
    $donation->setTaxCategoryId(app(TaxCategories::class)->getDefaultTaxCategory()->id);
    $donation->setShippingCategoryId(app(ShippingCategories::class)->getDefaultShippingCategory(app(Stores::class)->getPrimaryStore()->id)->id);
    expect(Elements::saveElement($donation))->toBeTrue();

    $this->donation = $donation;
});

it('prices a donation line item from an amount posted as a form value', function() {
    postJson(Url::actionUrl('commerce/cart/update'), [
        'purchasableId' => (string)$this->donation->id,
        // A form posts every value as a string.
        'options' => ['donationAmount' => '15'],
    ])
        ->assertOk()
        ->assertJsonPath('cart.itemSubtotal', 15)
        ->assertJsonPath('cart.total', 15);
});

it('rejects a donation amount that isn’t a number', function() {
    postJson(Url::actionUrl('commerce/cart/update'), [
        'purchasableId' => (string)$this->donation->id,
        'options' => ['donationAmount' => 'lots'],
    ])
        ->assertBadRequest()
        ->assertJsonPath('errors', fn(array $errors) => collect($errors)->flatten()->contains('Donation needs to be an amount.'));
});
