<?php

declare(strict_types=1);

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Adjuster\Discount as DiscountAdjuster;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Promotion\Coupons;
use CraftCms\Commerce\Promotion\Data\Coupon;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Promotion\Models\CustomerDiscountUse;
use CraftCms\Commerce\Promotion\Models\Discount as DiscountRecord;
use CraftCms\Commerce\Promotion\Models\EmailDiscountUse;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\DiscountsFixture;
use Illuminate\Support\Facades\DB;

/**
 * @param array<string, mixed> $orderConfig
 */
function discountsTestOrderCouponAvailable(array $orderConfig, bool $expectedResult, string $expectedExplanation = ''): void
{
    $order = new Order($orderConfig);

    $explanation = '';
    $result = app(Discounts::class)->orderCouponAvailable($order, $explanation);

    expect($result)->toBe($expectedResult);
    expect($explanation)->toBe($expectedExplanation);
}

function discountsTestUpdateDiscount(int $discountId, array $data): void
{
    DB::table(Table::DISCOUNTS)->where('id', $discountId)->update($data);
}

/**
 * Fills in default attributes shared by every discount in a dataset.
 *
 * @param array<string, array<string, mixed>> $discounts
 * @return array<string, array<string, mixed>>
 */
function discountsTestCreateDiscounts(array $discounts): array
{
    return collect($discounts)->mapWithKeys(fn(array $d, string $key) => [$key => array_merge([
        'name' => 'Discount - ' . $key,
        'perItemDiscount' => 1,
        'enabled' => true,
        'allCategories' => true,
        'allPurchasables' => true,
        'percentageOffSubject' => 'original',
    ], $d)])->all();
}

// ---------------------------------------------------------------------------------------------
// orderCouponAvailable()
// ---------------------------------------------------------------------------------------------

test('orderCouponAvailable is false for an unknown coupon code', function() {
    DiscountsFixture::seed();

    discountsTestOrderCouponAvailable(['couponCode' => 'invalid_coupon'], false, 'Coupon not valid.');
});

test('orderCouponAvailable is true for a valid coupon code with a signed-in customer', function() {
    $fixture = DiscountsFixture::seed();
    $this->actingAs($fixture->customer, 'craft');

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => $fixture->customer->id], true);
});

test('orderCouponAvailable is false once the discount has been disabled', function() {
    $fixture = DiscountsFixture::seed();
    $this->actingAs($fixture->customer, 'craft');
    discountsTestUpdateDiscount($fixture->discountWithCoupon->id, ['enabled' => false]);

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => $fixture->customer->id], false, 'Coupon not valid.');
});

test('orderCouponAvailable is false once the discount has expired', function() {
    $fixture = DiscountsFixture::seed();
    $this->actingAs($fixture->customer, 'craft');
    discountsTestUpdateDiscount($fixture->discountWithCoupon->id, ['dateTo' => '2019-05-01 10:21:33']);

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => $fixture->customer->id], false, 'Discount is out of date.');
});

test('orderCouponAvailable is false before the discount has started', function() {
    $fixture = DiscountsFixture::seed();
    $this->actingAs($fixture->customer, 'craft');
    $dateFrom = (new DateTime('now'))->add(new DateInterval('P2D'));
    discountsTestUpdateDiscount($fixture->discountWithCoupon->id, ['dateFrom' => $dateFrom->format('Y-m-d H:i:s')]);

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => $fixture->customer->id], false, 'Discount is out of date.');
});

test('orderCouponAvailable is false once the discount has reached its total use limit', function() {
    $fixture = DiscountsFixture::seed();
    $this->actingAs($fixture->customer, 'craft');
    // The fixture discount's totalDiscountUseLimit is 2.
    discountsTestUpdateDiscount($fixture->discountWithCoupon->id, ['totalDiscountUses' => 2]);

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => $fixture->customer->id], false, 'Discount use has reached its limit.');
});

test('orderCouponAvailable is false for a per-user-limited coupon with no signed-in customer', function() {
    DiscountsFixture::seed();
    // The fixture discount's perUserLimit is already 1.

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => null], false, 'This coupon is for registered users and limited to 1 uses.');
});

test('orderCouponAvailable is false once the signed-in customer has used their per-user limit', function() {
    $fixture = DiscountsFixture::seed();
    $this->actingAs($fixture->customer, 'craft');
    // The fixture discount's perUserLimit is already 1.

    $usage = new CustomerDiscountUse();
    $usage->customerId = $fixture->customer->id;
    $usage->discountId = $fixture->discountWithCoupon->id;
    $usage->uses = 1;
    $usage->save();

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => $fixture->customer->id], false, 'This coupon is for registered users and limited to 1 uses.');
});

test('orderCouponAvailable is false once the customer email has used its per-email limit', function() {
    $fixture = DiscountsFixture::seed();
    $this->actingAs($fixture->customer, 'craft');
    discountsTestUpdateDiscount($fixture->discountWithCoupon->id, ['perEmailLimit' => 1]);

    $usage = new EmailDiscountUse();
    $usage->email = $fixture->customer->email;
    $usage->discountId = $fixture->discountWithCoupon->id;
    $usage->uses = 1;
    $usage->save();

    discountsTestOrderCouponAvailable(['couponCode' => 'discount_1', 'customerId' => $fixture->customer->id], false, 'This coupon is limited to 1 uses.');
});

// ---------------------------------------------------------------------------------------------
// matchLineItem()
//
// Category-relation matching isn't covered: `matchLineItem()` unconditionally calls
// `craft\elements\Category::find()` whenever a discount has `allCategories: false`, which is
// currently broken (see COM-644/645/646/647), so it isn't exercised here.
// ---------------------------------------------------------------------------------------------

test('matchLineItem matches a fully-promotable line item against an all-purchasables, all-categories discount', function() {
    $order = new Order(['couponCode' => null]);
    $lineItem = new LineItem(['qty' => 2]);
    $lineItem->setPrice(10);
    $lineItem->setIsPromotable(true);
    $lineItem->setOrder($order);

    $discount = new Discount(['allPurchasables' => true, 'allCategories' => true]);

    expect(app(Discounts::class)->matchLineItem($lineItem, $discount))->toBeTrue();
});

test('matchLineItem does not match a line item on sale when the discount excludes promotional items', function() {
    $order = new Order(['couponCode' => null]);
    $lineItem = new LineItem(['qty' => 2]);
    $lineItem->setPrice(15);
    $lineItem->setPromotionalPrice(10);
    $lineItem->setOrder($order);

    $discount = new Discount(['excludeOnPromotion' => true]);

    expect(app(Discounts::class)->matchLineItem($lineItem, $discount))->toBeFalse();
});

test('matchLineItem does not match a line item that is not promotable', function() {
    $order = new Order(['couponCode' => null]);
    $lineItem = new LineItem(['qty' => 2]);
    $lineItem->setPrice(15);
    $lineItem->setIsPromotable(false);
    $lineItem->setOrder($order);

    $discount = new Discount();

    expect(app(Discounts::class)->matchLineItem($lineItem, $discount))->toBeFalse();
});

// ---------------------------------------------------------------------------------------------
// orderCompleteHandler()
// ---------------------------------------------------------------------------------------------

test('orderCompleteHandler records discount, customer, email, and coupon usage', function() {
    $fixture = DiscountsFixture::seed();
    $discountId = $fixture->discountWithCoupon->id;

    discountsTestUpdateDiscount($discountId, ['perUserLimit' => 0, 'perEmailLimit' => 0]);

    $order = new Order();
    $order->couponCode = 'discount_1';
    $order->setCustomerId($fixture->customer->id);

    $adjustment = new OrderAdjustment();
    $adjustment->name = 'Discount';
    $adjustment->type = DiscountAdjuster::ADJUSTMENT_TYPE;
    $adjustment->amount = -5;
    $adjustment->setSourceSnapshot(['discountUseId' => $discountId]);
    $order->setAdjustments([$adjustment]);

    app(Discounts::class)->orderCompleteHandler($order);

    expect((int) DB::table(Table::DISCOUNTS)->where('id', $discountId)->value('totalDiscountUses'))->toBe(1);

    $customerUse = DB::table(Table::CUSTOMER_DISCOUNTUSES)
        ->where('customerId', $fixture->customer->id)
        ->where('discountId', $discountId)
        ->first();
    expect($customerUse)->not->toBeNull();
    expect((int) $customerUse->uses)->toBe(1);

    $emailUse = DB::table(Table::EMAIL_DISCOUNTUSES)
        ->where('email', $order->getEmail())
        ->where('discountId', $discountId)
        ->first();
    expect($emailUse)->not->toBeNull();
    expect((int) $emailUse->uses)->toBe(1);

    expect((int) DB::table(Table::COUPONS)->where('code', 'discount_1')->value('uses'))->toBe(1);
});

test('orderCompleteHandler is a no-op when the order has no discount adjustments', function(?string $couponCode) {
    $order = new Order(['couponCode' => $couponCode]);

    expect(app(Discounts::class)->orderCompleteHandler($order))->toBeNull();
})->with([
    'no coupon code' => [null],
    'invalid coupon code' => ['i_dont_exist_as_coupon'],
]);

// ---------------------------------------------------------------------------------------------
// ensureSortOrder()
// ---------------------------------------------------------------------------------------------

test('ensureSortOrder resequences every discount for the store into a contiguous 1..N order', function() {
    $storeId = app(Stores::class)->getPrimaryStore()->id;

    $ids = [];
    for ($i = 1; $i <= 5; $i++) {
        $discount = new DiscountRecord();
        $discount->name = 'Dummy Discount ' . $i;
        // Randomise the sort order so ensureSortOrder() actually has something to fix.
        $discount->sortOrder = $i + random_int(1, 15);
        $discount->storeId = $storeId;
        $discount->enabled = true;
        $discount->allCategories = true;
        $discount->allPurchasables = true;
        $discount->percentageOffSubject = 'original';
        $discount->save();
        $ids[] = $discount->id;
    }

    app(Discounts::class)->ensureSortOrder($storeId);

    $discountRows = DB::table(Table::DISCOUNTS)->select(['id', 'sortOrder'])->orderBy('sortOrder')->get()->values();
    foreach ($discountRows as $i => $row) {
        expect((int) $row->sortOrder)->toBe($i + 1);
    }

    $allDiscounts = app(Discounts::class)->getAllDiscounts($storeId)->values();
    foreach ($allDiscounts as $i => $discount) {
        expect($discount->sortOrder)->toBe($i + 1);
    }

    foreach ($ids as $id) {
        app(Discounts::class)->deleteDiscountById($id);
    }
});

// ---------------------------------------------------------------------------------------------
// getAllActiveDiscounts()
//
// Datasets scoping a discount to specific categories (`allCategories: false` + `categoryIds`)
// are omitted — category-based discount matching is currently broken (see COM-644/645/646/647)
// so it isn't exercised here.
// ---------------------------------------------------------------------------------------------

test('getAllActiveDiscounts returns only the discounts a given order currently qualifies for', function(array|false $orderConfig, int $expectedCount, array $discountConfigs) {
    $fixture = DiscountsFixture::seed();

    $discountIds = [];
    foreach ($discountConfigs as $config) {
        $emailUses = $config['_emailUses'] ?? [];
        unset($config['_emailUses']);

        if (isset($config['purchasableIds'])) {
            $config['purchasableIds'] = Variant::find()->sku($config['purchasableIds'])->ids();
        }

        $config['storeId'] = $fixture->storeId;

        $discount = new Discount($config);
        expect(app(Discounts::class)->saveDiscount($discount))->toBeTrue();
        $discountIds[] = $discount->id;

        if ($discount->totalDiscountUses > 0) {
            DB::table(Table::DISCOUNTS)->where('id', $discount->id)->update(['totalDiscountUses' => $discount->totalDiscountUses]);
        }

        if (!empty($emailUses)) {
            foreach ($emailUses as $email => $uses) {
                $usage = new EmailDiscountUse();
                $usage->email = $email;
                $usage->discountId = $discount->id;
                $usage->uses = $uses;
                $usage->save();
            }
        }
    }

    if ($orderConfig === false) {
        $activeDiscounts = app(Discounts::class)->getAllActiveDiscounts();
    } else {
        $order = new Order(array_diff_key($orderConfig, ['_lineItems' => true]));

        if (isset($orderConfig['_lineItems'])) {
            $lineItems = [];
            foreach ($orderConfig['_lineItems'] as $sku => $qty) {
                $variant = Variant::find()->sku($sku)->one();
                $lineItems[] = app(LineItems::class)->create($order, [
                    'purchasableId' => $variant->id,
                    'options' => [],
                    'qty' => $qty,
                ]);
            }
            $order->setLineItems($lineItems);
        }

        $activeDiscounts = app(Discounts::class)->getAllActiveDiscounts($order);
    }

    expect($activeDiscounts)->toHaveCount($expectedCount);

    foreach ($discountIds as $id) {
        app(Discounts::class)->deleteDiscountById($id);
    }
})->with([
    'no order' => [false, 1, []],
    'order with valid coupon' => [['couponCode' => 'discount_1'], 1, []],
    'order with invalid coupon' => [['couponCode' => 'coupon_code_doesnt_exist'], 0, []],
    'order discounts by date' => [
        [],
        3,
        (function() {
            $yesterday = (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(12, 0)->modify('-1 day');
            $tomorrow = (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(12, 0)->modify('+1 day');

            return discountsTestCreateDiscounts([
                'date-from-valid' => ['dateFrom' => $yesterday],
                'date-from-invalid' => ['dateFrom' => $tomorrow],
                'date-to-valid' => ['dateTo' => $tomorrow],
                'date-to-invalid' => ['dateTo' => $yesterday],
                'date-to-from-valid' => ['dateFrom' => $yesterday, 'dateTo' => $tomorrow],
                'date-to-from-invalid' => ['dateFrom' => $tomorrow, 'dateTo' => (clone $tomorrow)->modify('+1 day')],
            ]);
        })(),
    ],
    'order discounts by total use limit' => [
        [],
        4,
        discountsTestCreateDiscounts([
            'total-limit-zero' => ['totalDiscountUseLimit' => 0],
            'total-limit-zero-with-uses' => ['totalDiscountUses' => 10, 'totalDiscountUseLimit' => 0],
            'total-limit-valid-with-no-uses' => ['totalDiscountUses' => 0, 'totalDiscountUseLimit' => 10],
            'total-limit-valid-with-uses' => ['totalDiscountUses' => 7, 'totalDiscountUseLimit' => 10],
            'total-limit-invalid-equals' => ['totalDiscountUses' => 10, 'totalDiscountUseLimit' => 10],
            'total-limit-invalid-extra' => ['totalDiscountUses' => 11, 'totalDiscountUseLimit' => 10],
        ]),
    ],
    'order discounts by email limit, order has no email' => [
        [],
        1,
        discountsTestCreateDiscounts([
            'total-limit-zero' => ['perEmailLimit' => 0],
            'total-limit' => ['perEmailLimit' => 1],
        ]),
    ],
    'order discounts by email limit' => [
        ['email' => 'per.email.limit@crafttest.com'],
        4,
        discountsTestCreateDiscounts([
            'total-limit-zero' => ['perEmailLimit' => 0],
            'total-limit-zero-with-uses' => ['_emailUses' => ['per.email.limit@crafttest.com' => 10], 'perEmailLimit' => 0],
            'total-limit-valid-with-no-uses' => ['perEmailLimit' => 10],
            'total-limit-valid-with-uses' => ['_emailUses' => ['per.email.limit@crafttest.com' => 7], 'perEmailLimit' => 10],
            'total-limit-invalid-equals' => ['_emailUses' => ['per.email.limit@crafttest.com' => 10], 'perEmailLimit' => 10],
            'total-limit-invalid-extra' => ['_emailUses' => ['per.email.limit@crafttest.com' => 11], 'perEmailLimit' => 10],
        ]),
    ],
    'purchase total limit, no line items' => [
        [],
        2,
        discountsTestCreateDiscounts([
            'purchase-total-zero' => ['purchaseTotal' => 0],
            'purchase-total-all-purchasables-false' => ['purchaseTotal' => 10, 'allPurchasables' => false, 'purchasableIds' => ['rad-hood']],
            // Dropped: 'purchase-total-all-categories-false' and 'purchase-total-both-all-false' —
            // both set `allCategories: false` with real `categoryIds`, which crashes (see above).
        ]),
    ],
    'purchase total limit, with a line item' => [
        ['_lineItems' => ['rad-hood' => 1]],
        // 'purchase-total-valid' (a $150 minimum spend against a single $123.99 rad-hood line
        // item) is correctly excluded — despite its name, "valid"/"invalid" here describe the
        // purchaseTotal value's format (a round number vs. a two-decimal one), not whether the
        // discount is expected to match.
        3,
        discountsTestCreateDiscounts([
            'purchase-total-zero' => ['purchaseTotal' => 0],
            'purchase-total-all-purchasables-false' => ['purchaseTotal' => 10, 'allPurchasables' => false, 'purchasableIds' => ['rad-hood']],
            // Dropped: 'purchase-total-all-categories-false' and 'purchase-total-both-all-false' (see above).
            'purchase-total-valid' => ['purchaseTotal' => 150],
            'purchase-total-invalid' => ['purchaseTotal' => 10.99],
        ]),
    ],
    'qty limits, no line items' => [
        [],
        4,
        discountsTestCreateDiscounts([
            'purchase-qty-zero' => ['purchaseQty' => 0],
            'max-qty-zero' => ['maxPurchaseQty' => 0],
            'both-zero' => ['purchaseQty' => 0, 'maxPurchaseQty' => 0],
            'purchase-qty-all-purchasables-false' => ['purchaseQty' => 4, 'allPurchasables' => false, 'purchasableIds' => ['rad-hood']],
            // Dropped: 'purchase-total-all-categories-false' and 'purchase-total-both-all-false' (see above).
        ]),
    ],
    'qty limits, with line items' => [
        ['_lineItems' => ['rad-hood' => 4]],
        6,
        discountsTestCreateDiscounts([
            'purchase-qty-zero' => ['purchaseQty' => 0],
            'max-qty-zero' => ['maxPurchaseQty' => 0],
            'both-zero' => ['purchaseQty' => 0, 'maxPurchaseQty' => 0],
            'purchase-qty-valid' => ['purchaseQty' => 3],
            'purchase-qty-invalid' => ['purchaseQty' => 5],
            'max-qty-valid' => ['maxPurchaseQty' => 10],
            'max-qty-invalid' => ['maxPurchaseQty' => 3],
            'both-valid' => ['purchaseQty' => 2, 'maxPurchaseQty' => 10],
            'both-invalid' => ['purchaseQty' => 10, 'maxPurchaseQty' => 14],
        ]),
    ],
    'purchasables restriction, one line item' => [
        ['_lineItems' => ['rad-hood' => 1]],
        3,
        discountsTestCreateDiscounts([
            'all-purchasables' => ['allPurchasables' => true],
            'one-to-one' => ['allPurchasables' => false, 'purchasableIds' => ['rad-hood']],
            'one-to-many' => ['allPurchasables' => false, 'purchasableIds' => ['rad-hood', 'hct-white']],
            'no-match' => ['allPurchasables' => false, 'purchasableIds' => ['hct-blue']],
        ]),
    ],
    'purchasables restriction, multiple line items' => [
        ['_lineItems' => ['rad-hood' => 1, 'hct-white' => 1]],
        2,
        discountsTestCreateDiscounts([
            'one' => ['allPurchasables' => false, 'purchasableIds' => ['rad-hood']],
            'many' => ['allPurchasables' => false, 'purchasableIds' => ['rad-hood', 'hct-white']],
            'no-match' => ['allPurchasables' => false, 'purchasableIds' => ['hct-blue']],
        ]),
    ],
]);

// ---------------------------------------------------------------------------------------------
// appendCouponCode()
// ---------------------------------------------------------------------------------------------

test('appendCouponCode adds string codes and Coupon models to a discount that requires a coupon code', function() {
    $discount = new Discount([
        'name' => 'Test Discount',
        'enabled' => true,
        'requireCouponCode' => true,
        'storeId' => app(Stores::class)->getPrimaryStore()->id,
        'perItemDiscount' => 10,
    ]);
    expect(app(Discounts::class)->saveDiscount($discount))->toBeTrue();

    expect(app(Discounts::class)->appendCouponCode($discount->id, 'TESTCODE123', 5))->toBeTrue();

    $coupons = app(Coupons::class)->getCouponsByDiscountId($discount->id);
    expect($coupons)->toHaveCount(1);
    expect($coupons[0]->code)->toBe('TESTCODE123');
    expect($coupons[0]->maxUses)->toBe(5);
    expect($coupons[0]->uses)->toBe(0);

    expect(app(Discounts::class)->appendCouponCode($discount->id, 'TESTCODE456'))->toBeTrue();
    expect(app(Coupons::class)->getCouponsByDiscountId($discount->id))->toHaveCount(2);

    $couponModel = new Coupon(['code' => 'MODELCODE789', 'maxUses' => 10, 'uses' => 0]);
    expect(app(Discounts::class)->appendCouponCode($discount->id, $couponModel))->toBeTrue();

    $coupons = app(Coupons::class)->getCouponsByDiscountId($discount->id);
    expect($coupons)->toHaveCount(3);

    $added = collect($coupons)->first(fn(Coupon $c) => $c->code === 'MODELCODE789');
    expect($added)->not->toBeNull();
    expect($added->maxUses)->toBe(10);
    expect($added->uses)->toBe(0);
});

test('appendCouponCode throws when the discount does not require a coupon code', function() {
    $discount = new Discount([
        'name' => 'Test Discount No Coupon',
        'enabled' => true,
        'requireCouponCode' => false,
        'storeId' => app(Stores::class)->getPrimaryStore()->id,
        'perItemDiscount' => 10,
    ]);
    expect(app(Discounts::class)->saveDiscount($discount))->toBeTrue();

    app(Discounts::class)->appendCouponCode($discount->id, 'SHOULDFAIL');
})->throws(RuntimeException::class, 'does not require a coupon code');

test('appendCouponCode throws for an unknown discount ID', function() {
    app(Discounts::class)->appendCouponCode(999999, 'SHOULDFAIL');
})->throws(RuntimeException::class, 'No discount exists with the ID "999999"');

test('appendCouponCode returns false and leaves validation errors on the coupon model when it is invalid', function() {
    $discount = new Discount([
        'name' => 'Test Discount',
        'enabled' => true,
        'requireCouponCode' => true,
        'storeId' => app(Stores::class)->getPrimaryStore()->id,
        'perItemDiscount' => 10,
    ]);
    expect(app(Discounts::class)->saveDiscount($discount))->toBeTrue();

    $couponModel = new Coupon(['code' => '', 'maxUses' => 10]);
    $result = app(Discounts::class)->appendCouponCode($discount->id, $couponModel);

    expect($result)->toBeFalse();
    expect($couponModel->errors()->isEmpty())->toBeFalse();
    expect($couponModel->errors()->has('code'))->toBeTrue();
});
