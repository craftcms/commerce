<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Twig\TwigRenderer;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\DigitalProduct\Events\LicenseKeyGenerating;
use CraftCms\Commerce\DigitalProduct\Licenses;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Purchasable\Purchasables;
use CraftCms\Commerce\Tests\Support\DigitalProductsFixture;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;

test('a digital product gets its SKU from the type’s SKU format and is a purchasable without shipping or inventory', function() {
    $type = DigitalProductsFixture::type();
    $product = DigitalProductsFixture::product($type, 'Field Guide', 15);

    expect($product->getSku())->toBe('EBOOK-field-guide')
        ->and($product->getType()->handle)->toBe('ebooks')
        ->and($product->getStatus())->toBe(DigitalProduct::STATUS_LIVE)
        ->and($product->getBasePrice())->toEqual(15.0)
        ->and($product->getIsShippable())->toBeFalse()
        ->and($product->hasFreeShipping())->toBeTrue()
        ->and(DigitalProduct::hasInventory())->toBeFalse()
        ->and($product->uri)->toBe('ebooks/field-guide')
        ->and(app(Purchasables::class)->getAllPurchasableElementTypes())->toContain(DigitalProduct::class);

    // A second product with the same title must not reuse the SKU
    $second = DigitalProductsFixture::product($type, 'Field Guide', 10);
    expect($second->getSku())->not->toBe('EBOOK-field-guide')
        ->and($second->getSku())->toStartWith('EBOOK-field-guide-');

    expect(DigitalProduct::find()->type('ebooks')->sku('EBOOK-field-guide')->ids())->toBe([$product->id]);
});

test('craft.digitalProducts applies query criteria, as the plugin’s 6.0 variable does', function() {
    $ebook = DigitalProductsFixture::product(DigitalProductsFixture::type(), 'Field Guide');
    $course = DigitalProductsFixture::product(DigitalProductsFixture::type('courses', 'COURSE-{slug}'), 'Masterclass');

    $license = new License();
    $license->productId = $course->id;
    $license->ownerEmail = 'owner@example.com';
    expect(Elements::saveElement($license))->toBeTrue();

    $render = fn(string $template) => app(TwigRenderer::class)->renderString($template);

    expect($render("{{ craft.digitalProducts.products({type: 'courses'}).ids()|join(',') }}"))->toBe((string)$course->id)
        ->and($render('{{ craft.digitalProducts.products().count() }}'))->toBe('2')
        ->and($render("{{ craft.digitalProducts.licenses({productId: $ebook->id}).count() }}"))->toBe('0')
        ->and($render("{{ craft.digitalProducts.licenses({productId: $course->id}).ids()|join(',') }}"))->toBe((string)$license->id);
});

test('a pending digital product isn’t live or available', function() {
    $type = DigitalProductsFixture::type();
    $product = new DigitalProduct();
    $product->typeId = $type->id;
    $product->title = 'Coming Soon';
    $product->postDate = new DateTime('+1 week');
    $product->setBasePrice(5);
    expect(Elements::saveElement($product))->toBeTrue();

    expect($product->getStatus())->toBe(DigitalProduct::STATUS_PENDING)
        ->and($product->getIsAvailable())->toBeFalse()
        ->and(DigitalProduct::find()->id($product->id)->exists())->toBeFalse()
        ->and(DigitalProduct::find()->id($product->id)->status(DigitalProduct::STATUS_PENDING)->exists())->toBeTrue();
});

test('completing an order issues one license per unit to the customer', function() {
    app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid = false;
    app(Plugin::class)->getSettings()->licenseKeyLength = 10;
    app(Plugin::class)->getSettings()->licenseKeyCharacters = 'AB';

    $fixture = OrdersFixture::seed();
    $type = DigitalProductsFixture::type();
    $product = DigitalProductsFixture::product($type);
    $customer = Users::ensureUserByEmail('licensee@example.com');

    $order = new Order();
    $order->setCustomer($customer);
    $order->setLineItems([
        app(LineItems::class)->create($order, ['purchasableId' => $product->id, 'qty' => 2]),
        app(LineItems::class)->create($order, ['purchasableId' => $fixture->white->id, 'qty' => 1]),
    ]);

    expect($order->markAsComplete())->toBeTrue();

    /** @var License[] $licenses */
    $licenses = License::find()->orderId($order->id)->all();

    expect($licenses)->toHaveCount(2);

    foreach ($licenses as $license) {
        expect($license->productId)->toBe($product->id)
            ->and($license->userId)->toBe($customer->id)
            ->and($license->ownerEmail)->toBe('licensee@example.com')
            ->and($license->licenseKey)->toMatch('/^[AB]{10}$/')
            ->and($license->getProduct()?->id)->toBe($product->id);
    }

    expect($licenses[0]->licenseKey)->not->toBe($licenses[1]->licenseKey)
        ->and(License::find()->product($product)->owner($customer)->count())->toBe(2);
});

test('licenses aren’t issued on completion when they’re issued on payment', function() {
    app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid = true;

    $type = DigitalProductsFixture::type();
    $product = DigitalProductsFixture::product($type);

    $order = new Order();
    $order->setCustomer(Users::ensureUserByEmail('licensee@example.com'));
    $order->setLineItems([app(LineItems::class)->create($order, ['purchasableId' => $product->id, 'qty' => 1])]);

    expect($order->markAsComplete())->toBeTrue()
        ->and(License::find()->orderId($order->id)->exists())->toBeFalse();
});

test('a license key listener can provide the key', function() {
    Event::listen(LicenseKeyGenerating::class, fn(LicenseKeyGenerating $event) => $event->licenseKey = 'CUSTOM-' . $event->license->productId);

    $product = DigitalProductsFixture::product(DigitalProductsFixture::type());

    $license = new License();
    $license->productId = $product->id;
    $license->ownerEmail = 'someone@example.com';

    expect(Elements::saveElement($license))->toBeTrue()
        ->and($license->licenseKey)->toBe('CUSTOM-' . $product->id);
});

test('a license needs an owner email or a user', function() {
    $product = DigitalProductsFixture::product(DigitalProductsFixture::type());

    $license = new License();
    $license->productId = $product->id;
    $license->ruleset->useScenario(ElementRules::SCENARIO_LIVE);

    expect(Elements::saveElement($license))->toBeFalse()
        ->and($license->errors()->has('userId'))->toBeTrue();
});

test('activating a user assigns them the unassigned licenses for their email', function() {
    app(Plugin::class)->getSettings()->autoAssignLicensesOnPurchase = false;

    $product = DigitalProductsFixture::product(DigitalProductsFixture::type());

    $license = new License();
    $license->productId = $product->id;
    $license->ownerEmail = 'new-customer@example.com';
    expect(Elements::saveElement($license))->toBeTrue()
        ->and($license->userId)->toBeNull();

    $user = Users::ensureUserByEmail('new-customer@example.com');
    app(Licenses::class)->handleUserActivated(new \CraftCms\Cms\User\Events\UserActivated($user));

    expect(License::find()->id($license->id)->one()->userId)->toBe($user->id);
});

test('guests can’t pay for orders with digital products when a logged-in user is required', function() {
    app(Plugin::class)->getSettings()->requireLoggedInUserForDigitalProducts = true;

    $product = DigitalProductsFixture::product(DigitalProductsFixture::type());

    $order = new Order();
    $order->setLineItems([app(LineItems::class)->create($order, ['purchasableId' => $product->id, 'qty' => 1])]);

    $event = new \CraftCms\Commerce\Payment\Events\PaymentProcessing($order, Mockery::mock(\CraftCms\Commerce\Payment\Forms\BasePaymentForm::class));
    app(Licenses::class)->handlePaymentProcessing($event);
    expect($event->isValid)->toBeFalse();

    app(Plugin::class)->getSettings()->requireLoggedInUserForDigitalProducts = false;
    $event = new \CraftCms\Commerce\Payment\Events\PaymentProcessing($order, Mockery::mock(\CraftCms\Commerce\Payment\Forms\BasePaymentForm::class));
    app(Licenses::class)->handlePaymentProcessing($event);
    expect($event->isValid)->toBeTrue();
});

test('existingLicenses eager loads only the current user’s licenses', function() {
    $type = DigitalProductsFixture::type();
    $product = DigitalProductsFixture::product($type);
    $owner = Users::ensureUserByEmail('owner@example.com');
    $other = Users::ensureUserByEmail('other@example.com');

    foreach ([$owner, $other] as $user) {
        $license = new License();
        $license->productId = $product->id;
        $license->userId = $user->id;
        expect(Elements::saveElement($license))->toBeTrue();
    }

    $this->actingAs($owner);

    $loaded = DigitalProduct::find()->id($product->id)->with('existingLicenses')->one();

    expect($loaded->getExistingLicenses())->toHaveCount(1)
        ->and($loaded->getExistingLicenses()[0]->userId)->toBe($owner->id);
});
