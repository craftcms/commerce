<?php

declare(strict_types=1);

use CraftCms\Commerce\Promotion\Coupons;
use CraftCms\Commerce\Promotion\Data\Coupon;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Promotion\Models\Coupon as CouponRecord;
use CraftCms\Commerce\Tests\Support\DiscountsFixture;

test('getAllCodes returns every coupon code across all discounts', function() {
    DiscountsFixture::seed();
    $codes = app(Coupons::class)->getAllCodes();

    expect($codes)->toBeArray()->not->toBeEmpty();
    expect($codes)->toContain('discount_1');
});

test('getCouponByCode returns the matching coupon, or null when the code is unknown', function(string $code, ?string $expectedCode) {
    DiscountsFixture::seed();
    $coupon = app(Coupons::class)->getCouponByCode($code);

    if ($expectedCode === null) {
        expect($coupon)->toBeNull();
    } else {
        expect($coupon)->toBeInstanceOf(Coupon::class);
        expect($coupon->code)->toBe($expectedCode);
    }
})->with([
    'existing code' => ['discount_1', 'discount_1'],
    'unknown code' => ['invalid_code', null],
]);

test('getCouponsByDiscountId returns only the coupons for that discount, or none for an unknown ID', function() {
    $fixture = DiscountsFixture::seed();
    $coupons = app(Coupons::class);

    expect($coupons->getCouponsByDiscountId(0))->toBe([]);

    $result = $coupons->getCouponsByDiscountId($fixture->discountWithCoupon->id);
    expect($result)->not->toBeEmpty();
    expect(array_map(fn(Coupon $c) => $c->code, $result))->toContain('discount_1');
});

test('generateCouponCodes generates the requested count of codes matching the format', function(int $count, string $format, array $existingCodes) {
    DiscountsFixture::seed();
    $codes = app(Coupons::class)->generateCouponCodes($count, $format, $existingCodes);

    expect($codes)->toHaveCount($count);
    expect($codes[0])->toMatch('/' . str_replace(Coupons::COUPON_FORMAT_REPLACEMENT_CHAR, '.', $format) . '/');
})->with([
    'simple format, no existing codes' => [10, 'commerce_####', []],
    'restrictive format with an excluded existing code' => [25, 'commerce_#_coupons', ['commerce_A_coupons']],
]);

test('generateCouponCodes throws when the format cannot produce enough unique codes', function() {
    DiscountsFixture::seed();

    app(Coupons::class)->generateCouponCodes(45, 'commerce_#', []);
})->throws(Exception::class);

test('saveCoupon persists a new coupon but rejects a duplicate code', function(string $code, bool $expectedResult) {
    $fixture = DiscountsFixture::seed();
    $coupon = new Coupon(['code' => $code, 'discountId' => $fixture->discountWithCoupon->id]);

    $result = app(Coupons::class)->saveCoupon($coupon, true);

    expect($result)->toBe($expectedResult);

    if ($expectedResult) {
        expect($coupon->id)->not->toBeNull();
    } else {
        expect($coupon->id)->toBeNull();
    }
})->with([
    'new, unique code' => ['test_code', true],
    'code already in use' => ['discount_1', false],
]);

test('deleteCouponById removes the coupon record', function() {
    $fixture = DiscountsFixture::seed();

    $couponRecord = new CouponRecord();
    $couponRecord->code = 'commerce_test_code';
    $couponRecord->discountId = $fixture->discountWithCoupon->id;
    $couponRecord->uses = 0;
    $couponRecord->maxUses = null;
    $couponRecord->save();

    expect(app(Coupons::class)->deleteCouponById($couponRecord->id))->toBeTrue();
});

test('saveDiscountCoupons adds new coupons and removes ones no longer on the discount', function() {
    $fixture = DiscountsFixture::seed();

    $discount = app(Discounts::class)->getDiscountById($fixture->discountWithCoupon->id);
    $newCoupon = new Coupon(['discountId' => $discount->id, 'code' => 'new_commerce_coupon', 'uses' => 0]);
    $discount->setCoupons([...$discount->getCoupons(), $newCoupon]);

    expect(app(Coupons::class)->saveDiscountCoupons($discount))->toBeTrue();

    // Clearing the coupon list entirely should delete every coupon that was on the discount.
    $discount->setCoupons([]);
    expect(app(Coupons::class)->saveDiscountCoupons($discount))->toBeTrue();
    expect(app(Coupons::class)->getCouponsByDiscountId($discount->id))->toBe([]);
});

test('saveDiscountCoupons throws when the discount has not been saved yet', function() {
    app(Coupons::class)->saveDiscountCoupons(new Discount());
})->throws(RuntimeException::class);
