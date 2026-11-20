<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Twig\TwigRenderer;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Http\Controllers\Settings\ProductTypesController;
use CraftCms\Commerce\License\Elements\License;
use CraftCms\Commerce\License\Licenses;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Tests\Support\LicensesFixture;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;

/**
 * Completes an order for one line item, with optional line item options.
 */
function completeLicenseOrder(string $email, Variant $variant, int $qty = 1, array $options = []): Order
{
    $order = new Order();
    $order->setCustomer(Users::ensureUserByEmail($email));
    $order->setLineItems([app(LineItems::class)->create($order, ['purchasableId' => $variant->id, 'qty' => $qty, 'options' => $options])]);

    expect($order->markAsComplete())->toBeTrue();

    return $order;
}

function expiringType(string $duration, bool $allowOverride = false): ProductType
{
    $type = LicensesFixture::productType();
    $type->hasLicenseExpiry = true;
    $type->licenseExpiryDuration = $duration;
    $type->allowLicenseExpiryOverride = $allowOverride;
    app(ProductTypes::class)->saveProductType($type) or throw new RuntimeException(json_encode($type->errors()->all()));

    return app(ProductTypes::class)->getProductTypeById($type->id);
}

function saveVariant(Variant $variant, array $attributes): Variant
{
    foreach ($attributes as $name => $value) {
        $variant->$name = $value;
    }
    Elements::saveElement($variant) or throw new RuntimeException(json_encode($variant->errors()->all()));

    return Variant::find()->id($variant->id)->one();
}

beforeEach(function() {
    app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid = false;
});

test('licenses expire after the product type duration, ignoring variant settings unless the type allows overrides', function() {
    $type = expiringType('P1M');
    $variant = saveVariant(LicensesFixture::variant(LicensesFixture::product($type), 'EDITOR-PRO'), [
        'licenseExpires' => false,
        'licenseExpiryDuration' => 'P7D',
    ]);

    $before = new DateTime();
    $license = License::find()->orderId(completeLicenseOrder('a@example.com', $variant)->id)->one();

    // A month, not 30 days, so P1M and P30D give different dates in most months
    expect($license->expiryDate->format('Y-m-d'))->toBe((clone $before)->modify('+1 month')->format('Y-m-d'))
        ->and($license->getStatus())->toBe(License::STATUS_ACTIVE);
});

test('variants can override the duration or turn expiry off when the product type allows it', function() {
    $type = expiringType('P30D', allowOverride: true);
    $product = LicensesFixture::product($type);
    $default = LicensesFixture::variant($product, 'DEFAULT');
    $weekly = saveVariant(LicensesFixture::variant($product, 'WEEKLY'), ['licenseExpiryDuration' => 'P7D']);
    $perpetual = saveVariant(LicensesFixture::variant($product, 'PERPETUAL'), ['licenseExpires' => false, 'licenseExpiryDuration' => 'P7D']);

    $today = new DateTime();
    $expiry = fn(Variant $variant) => License::find()->orderId(completeLicenseOrder('b@example.com', $variant)->id)->one()->expiryDate?->format('Y-m-d');

    expect($expiry($default))->toBe((clone $today)->modify('+30 days')->format('Y-m-d'))
        ->and($expiry($weekly))->toBe((clone $today)->modify('+7 days')->format('Y-m-d'))
        ->and($expiry($perpetual))->toBeNull();

    // Turning expiry off for the type wins over a variant's leftover duration
    $type->hasLicenseExpiry = false;
    app(ProductTypes::class)->saveProductType($type);
    expect($expiry(Variant::find()->id($weekly->id)->one()))->toBeNull();
});

test('a license keeps the expiry date it was issued with', function() {
    $type = expiringType('P10D');
    $variant = LicensesFixture::variant(LicensesFixture::product($type), 'EDITOR-PRO');
    $license = License::find()->orderId(completeLicenseOrder('c@example.com', $variant)->id)->one();
    $issuedExpiry = $license->expiryDate->format(DATE_ATOM);

    $type->licenseExpiryDuration = 'P1Y';
    app(ProductTypes::class)->saveProductType($type);

    expect(License::find()->id($license->id)->one()->expiryDate->format(DATE_ATOM))->toBe($issuedExpiry);
});

test('the product type rejects durations that are not positive ISO 8601 durations', function() {
    $type = LicensesFixture::productType();
    $type->hasLicenseExpiry = true;

    foreach (['365', 'P0D', 'one year', null] as $duration) {
        $type->licenseExpiryDuration = $duration;
        expect(app(ProductTypes::class)->saveProductType($type))->toBeFalse()
            ->and($type->errors()->has('licenseExpiryDuration'))->toBeTrue();
    }
});

test('after a failed save, the product type screen keeps the license expiry fields the submitted toggles revealed', function() {
    prioritizeCommerceRoutes();
    $this->withoutVite();
    $type = LicensesFixture::productType();
    actingAs(User::find()->admin(true)->one());
    $hasDurationField = fn(Collection $nodes) => str_contains(json_encode($nodes), '"licenseExpiryDuration"');

    get($type->getCpEditUrl())
        ->assertInertia(fn(AssertableInertia $page) => $page->where('ui.nodes', fn(Collection $nodes) => !$hasDurationField($nodes)));

    from($type->getCpEditUrl())->post(action([ProductTypesController::class, 'store']), [
        'productTypeId' => $type->id,
        'name' => $type->name,
        'handle' => $type->handle,
        'hasLicenses' => '1',
        'hasLicenseExpiry' => '1',
        'licenseExpiryDuration' => '365',
        'sites' => [Sites::getPrimarySite()->handle => ['enabledByDefault' => '1']],
    ])->assertRedirect($type->getCpEditUrl());

    // A real request starts with no product types in memory, so the screen can't see the edits the failed save made to the cached model
    app()->forgetInstance(ProductTypes::class);

    get($type->getCpEditUrl())
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('ui.values.licenseExpiryDuration', '365')
            ->where('ui.nodes', fn(Collection $nodes) => $hasDurationField($nodes)));
});

test('expired licenses are hidden from queries unless asked for', function() {
    $variant = LicensesFixture::variant(LicensesFixture::product(expiringType('P1Y')), 'EDITOR-PRO');
    $order = completeLicenseOrder('d@example.com', $variant, qty: 2);
    [$expired, $active] = License::find()->orderId($order->id)->orderBy('elements.id')->all();

    $expired->expiryDate = new DateTime('-1 minute');
    Elements::saveElement($expired);

    expect(License::find()->orderId($order->id)->ids())->toBe([$active->id])
        ->and(License::find()->orderId($order->id)->status('expired')->ids())->toBe([$expired->id])
        ->and(License::find()->orderId($order->id)->status(null)->count())->toBe(2)
        ->and(License::find()->orderId($order->id)->status(null)->expiryDate('< now')->ids())->toBe([$expired->id])
        ->and(License::find()->id($expired->id)->status(null)->one()->getStatus())->toBe(License::STATUS_EXPIRED)
        ->and(app(TwigRenderer::class)->renderString("{{ craft.licenses({orderId: $order->id}).count() }}|{{ craft.licenses({orderId: $order->id}).status(null).count() }}"))->toBe('1|2');
});

describe('renewals', function() {
    beforeEach(function() {
        $this->variant = LicensesFixture::variant(LicensesFixture::product(expiringType('P1M')), 'EDITOR-PRO');
        $this->license = License::find()->orderId(completeLicenseOrder('owner@example.com', $this->variant)->id)->one();
    });

    test('a line item naming a license the customer owns extends it from its expiry date', function() {
        $previousExpiry = $this->license->expiryDate;

        completeLicenseOrder('owner@example.com', $this->variant, options: [Licenses::RENEW_LICENSE_KEY_OPTION => $this->license->licenseKey]);

        expect(License::find()->status(null)->variantId($this->variant->id)->count())->toBe(1)
            ->and(License::find()->id($this->license->id)->one()->expiryDate->format('Y-m-d'))
            ->toBe((clone $previousExpiry)->modify('+1 month')->format('Y-m-d'));
    });

    test('an expired license is extended from now', function() {
        $this->license->expiryDate = new DateTime('-3 months');
        Elements::saveElement($this->license);

        completeLicenseOrder('owner@example.com', $this->variant, options: [Licenses::RENEW_LICENSE_KEY_OPTION => $this->license->licenseKey]);

        expect(License::find()->id($this->license->id)->one()->expiryDate->format('Y-m-d'))
            ->toBe((new DateTime('+1 month'))->format('Y-m-d'));
    });

    test('another customer’s license key, a key for another variant, or a quantity over 1 fails validation', function() {
        $other = LicensesFixture::variant(Variant::find()->id($this->variant->id)->one()->getOwner(), 'EDITOR-LITE');

        foreach ([
            ['someone@example.com', $this->variant, 1],
            ['owner@example.com', $other, 1],
            ['owner@example.com', $this->variant, 2],
        ] as [$email, $variant, $qty]) {
            $order = new Order();
            $order->setCustomer(Users::ensureUserByEmail($email));
            $lineItem = app(LineItems::class)->create($order, [
                'purchasableId' => $variant->id,
                'qty' => $qty,
                'options' => [Licenses::RENEW_LICENSE_KEY_OPTION => $this->license->licenseKey],
            ]);
            $order->setLineItems([$lineItem]);

            expect($lineItem->validate())->toBeFalse();
        }
    });

    test('without the option, buying the same variant again issues a new license', function() {
        completeLicenseOrder('owner@example.com', $this->variant);

        expect(License::find()->variantId($this->variant->id)->count())->toBe(2);
    });
});
