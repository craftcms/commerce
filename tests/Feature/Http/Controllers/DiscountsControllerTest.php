<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Promotion\Coupons;
use CraftCms\Commerce\Promotion\Data\Coupon;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Promotion\Discounts;
use CraftCms\Commerce\Tests\Support\DiscountsFixture;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

/**
 * @param array<array{id?: int|string|null, code: string, uses?: int|string, maxUses?: int|string|null}>|null $coupons
 * @return array<string, mixed>
 */
function discountSaveBody(Discount $discount, ?array $coupons, array $overrides = []): array
{
    return array_filter([
        'id' => $discount->id,
        'storeId' => $discount->storeId,
        'name' => $discount->name,
        'enabled' => $discount->enabled,
        'requireCouponCode' => true,
        'couponFormat' => $discount->couponFormat,
        'coupons' => $coupons,
        'percentageOffSubject' => $discount->percentageOffSubject,
        'appliedTo' => $discount->appliedTo,
        'baseDiscount' => ['value' => 0],
        'perItemDiscount' => ['value' => 0],
        'purchaseTotal' => ['value' => 0],
        'percentDiscount' => 0,
        ...$overrides,
    ], fn($value) => $value !== null);
}

/** @return string[] */
function discountCouponCodes(int $discountId): array
{
    return array_map(fn(Coupon $coupon) => $coupon->code, app(Coupons::class)->getCouponsByDiscountId($discountId));
}

it('adds, updates and removes coupons when saving a discount', function() {
    $discount = DiscountsFixture::seed()->discountWithCoupon;
    $existing = app(Coupons::class)->getCouponsByDiscountId($discount->id)[0];

    postJson(Url::actionUrl('commerce/discounts/save'), discountSaveBody($discount, [
        ['id' => $existing->id, 'code' => $existing->code, 'uses' => '3', 'maxUses' => '10'],
        ['id' => '', 'code' => 'SUMMER_ABCD', 'uses' => '0', 'maxUses' => ''],
    ]))->assertOk();

    $coupons = collect(app(Coupons::class)->getCouponsByDiscountId($discount->id))->keyBy('code');

    expect($coupons->keys()->sort()->values()->all())->toBe(['SUMMER_ABCD', 'discount_1'])
        ->and($coupons['discount_1']->id)->toBe($existing->id)
        ->and($coupons['discount_1']->uses)->toBe(3)
        ->and($coupons['discount_1']->maxUses)->toBe(10)
        ->and($coupons['SUMMER_ABCD']->maxUses)->toBeNull();

    postJson(Url::actionUrl('commerce/discounts/save'), discountSaveBody($discount, []))->assertOk();

    expect(discountCouponCodes($discount->id))->toBe([]);
});

it('leaves coupons untouched when none are posted', function() {
    $discount = DiscountsFixture::seed()->discountWithCoupon;

    postJson(Url::actionUrl('commerce/discounts/save'), discountSaveBody($discount, null))->assertOk();

    expect(discountCouponCodes($discount->id))->toBe(['discount_1']);
});

it('allows removing a coupon and re-adding the same code in one save', function() {
    $discount = DiscountsFixture::seed()->discountWithCoupon;

    postJson(Url::actionUrl('commerce/discounts/save'), discountSaveBody($discount, [
        ['id' => '', 'code' => 'discount_1', 'uses' => 0, 'maxUses' => ''],
    ]))->assertOk();

    expect(discountCouponCodes($discount->id))->toBe(['discount_1']);
});

it('does not move another discount’s coupon onto this one', function() {
    $fixture = DiscountsFixture::seed();
    $other = $fixture->discountWithCoupon;
    $otherCoupon = app(Coupons::class)->getCouponsByDiscountId($other->id)[0];

    $discount = new Discount(['name' => 'Second', 'storeId' => $other->storeId, 'allPurchasables' => true, 'allCategories' => true]);
    app(Discounts::class)->saveDiscount($discount);

    postJson(Url::actionUrl('commerce/discounts/save'), discountSaveBody($discount, [
        ['id' => $otherCoupon->id, 'code' => 'hijacked', 'uses' => 0, 'maxUses' => ''],
    ]))->assertOk();

    expect(discountCouponCodes($other->id))->toBe(['discount_1'])
        ->and(discountCouponCodes($discount->id))->toBe(['hijacked']);
});

it('rejects invalid coupon codes without saving the discount', function(array $codes, string $message) {
    $discount = DiscountsFixture::seed()->discountWithCoupon;

    $second = new Discount(['name' => 'Second', 'storeId' => $discount->storeId, 'allPurchasables' => true, 'allCategories' => true]);
    app(Discounts::class)->saveDiscount($second);

    postJson(Url::actionUrl('commerce/discounts/save'), discountSaveBody($second, array_map(
        fn(string $code) => ['id' => '', 'code' => $code, 'uses' => 0, 'maxUses' => ''],
        $codes,
    ), ['name' => 'Renamed']))
        ->assertBadRequest()
        ->assertJsonPath('errors.coupons.0', $message);

    expect(discountCouponCodes($second->id))->toBe([])
        ->and(app(Discounts::class)->getDiscountById($second->id)->name)->toBe('Second');
})->with([
    'blank' => [['valid', ' '], 'Coupon codes cannot be blank.'],
    'duplicate ignoring case' => [['SAME', 'same'], 'Coupon codes must be unique.'],
    'used by another discount' => [['DISCOUNT_1'], 'Coupon code “discount_1” is already in use by discount “Discount 1”.'],
]);

it('generates coupon codes matching the format and avoiding existing codes', function() {
    DiscountsFixture::seed();

    $response = postJson(Url::actionUrl('commerce/discounts/generate-coupons'), [
        'count' => 5,
        'format' => 'SUMMER_####',
        'existingCodes' => ['SUMMER_AAAA'],
    ])->assertOk();

    $coupons = $response->json('coupons');

    expect($coupons)->toHaveCount(5)
        ->each->toMatch('/^SUMMER_[A-Z]{4}$/')
        ->and($coupons)->not->toContain('SUMMER_AAAA');
});

it('rejects invalid coupon generation requests', function(array $body, string $errorKey) {
    postJson(Url::actionUrl('commerce/discounts/generate-coupons'), $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);
})->with([
    'format without #' => [['count' => 1, 'format' => 'SUMMER'], 'format'],
    'count too high' => [['count' => 401, 'format' => '####'], 'count'],
    'count too low' => [['count' => 0, 'format' => '####'], 'count'],
]);

it('requires discount permissions to generate coupon codes', function() {
    $user = new User(['username' => 'no-permissions', 'email' => 'no-permissions@example.com']);
    Elements::saveElement($user);
    actingAs($user);

    postJson(Url::actionUrl('commerce/discounts/generate-coupons'), ['count' => 1, 'format' => '####'])
        ->assertForbidden();
});

it('renders the coupons table and generator only when a coupon code is required', function(bool $requireCouponCode) {
    $discount = DiscountsFixture::seed()->discountWithCoupon;

    $response = postJson(Url::actionUrl('commerce/discounts/render-form'), [
        'values' => ['id' => $discount->id, 'storeId' => $discount->storeId, 'requireCouponCode' => $requireCouponCode],
        'scope' => [],
    ])->assertOk();

    $form = json_encode($response->json('ui'));

    expect(str_contains($form, 'commerce:coupon-generator'))->toBe($requireCouponCode)
        ->and(str_contains($form, 'Add a coupon'))->toBe($requireCouponCode)
        ->and($response->json('ui.values.coupons.0.code'))->toBe('discount_1');
})->with([
    'required' => true,
    'not required' => false,
]);
