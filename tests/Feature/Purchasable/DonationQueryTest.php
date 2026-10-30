<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use CraftCms\Commerce\Purchasable\Queries\DonationQuery;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\TaxCategories;
use Illuminate\Support\Facades\DB;

test('find returns a DonationQuery', function() {
    expect(Donation::find())->toBeInstanceOf(DonationQuery::class);
    expect(Donation::find())->toBeInstanceOf(PurchasableQuery::class);
});

test('availableForPurchase filters by whether the donation purchasable can be bought', function(bool $availableForPurchase) {
    $donation = null;

    // Make sure a donation purchasable exists to query against.
    if (DB::table(Table::DONATIONS)->count() === 0) {
        $primaryStore = app(Stores::class)->getPrimaryStore();
        $donation = new Donation();
        $donation->siteId = Sites::getPrimarySite()->id;
        $donation->sku = 'DONATION-CC5';
        $donation->availableForPurchase = false;
        $donation->setTaxCategoryId(app(TaxCategories::class)->getDefaultTaxCategory()->id);
        $donation->setShippingCategoryId(app(ShippingCategories::class)->getDefaultShippingCategory($primaryStore->id)->id);
        expect(Elements::saveElement($donation))->toBeTrue();
    }

    $query = Donation::find()->availableForPurchase($availableForPurchase)->status(null);
    $all = $query->all();

    // The donation purchasable above is never available for purchase.
    expect($all)->toHaveCount($availableForPurchase ? 0 : 1);

    $toDelete = $donation ?? ($all[0] ?? null);
    if ($toDelete !== null) {
        Elements::deleteElement($toDelete, true);
    }
})->with([
    'available' => [true],
    'not-available' => [false],
]);
