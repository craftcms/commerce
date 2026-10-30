<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Notification;

use function CraftCms\Cms\t;

/**
 * Builds a fresh, unsaved order for the given customer email using the product/variant seeded by
 * OrdersFixture. ensureUserByEmail() returns an existing user for the address when one already
 * exists, so a test can pre-activate a customer before calling this to simulate checkout by an
 * existing credentialed account rather than a new guest.
 */
function createCustomerOrder(OrdersFixture $fixture, string $email): Order
{
    $user = Users::ensureUserByEmail($email);

    $order = new Order();
    $order->number = bin2hex(random_bytes(16));
    $order->storeId = $fixture->storeId;
    $order->setCustomer($user);

    $lineItem = app(LineItems::class)->create($order, [
        'purchasableId' => $fixture->white->id,
        'qty' => 4,
        'note' => 'My note',
    ]);
    $order->setLineItems([$lineItem]);

    return $order;
}

/**
 * Ensures a user exists for the given email and is active, so getIsCredentialed() reports true.
 */
function ensureCredentialedCustomer(string $email): User
{
    $user = Users::ensureUserByEmail($email);
    Users::activateUser($user);

    return $user;
}

beforeEach(function() {
    $this->fixture = OrdersFixture::seed();
});

test('orderCompleteHandler activates the customer account only when registration is requested or the customer was already credentialed', function(string $email, bool $alreadyActive, bool $register) {
    if ($alreadyActive) {
        ensureCredentialedCustomer($email);
    }

    // Registering a new account sends an activation email; fake notifications so that send
    // doesn't need a real mailer/queue round-trip.
    Notification::fake();

    $order = createCustomerOrder($this->fixture, $email);
    $originallyCredentialed = $order->getCustomer()->getIsCredentialed();
    $order->registerUserOnOrderComplete = $register;

    expect($order->markAsComplete())->toBeTrue();

    $foundUser = User::find()->email($email)->status(null)->one();
    expect($foundUser)->not->toBeNull();
    expect($foundUser->getIsCredentialed())->toBe($register || $originallyCredentialed);
})->with([
    'dont-register-guest' => ['register.guest@crafttest.com', false, false],
    'register-guest' => ['register.guest@crafttest.com', false, true],
    'register-already-credentialed-user' => ['already.credentialed@crafttest.com', true, true],
    'dont-register-already-credentialed-user' => ['already.credentialed@crafttest.com', true, false],
]);

$guestBillingAddress = [
    'fullName' => 'Guest Billing',
    'addressLine1' => '1 Main Billing Street',
    'locality' => 'Billingsville',
    'administrativeArea' => 'OR',
    'postalCode' => '12345',
    'countryCode' => 'US',
];
$guestShippingAddress = [
    'fullName' => 'Guest Shipping',
    'addressLine1' => '1 Main Shipping Street',
    'locality' => 'Shippingsville',
    'administrativeArea' => 'AL',
    'postalCode' => '98765',
    'countryCode' => 'US',
];

test('orderCompleteHandler copies the order\'s addresses into a newly-registered guest\'s address book', function(?array $billingAddress, ?array $shippingAddress, int $addressCount) {
    $email = 'guest.person@crafttest.com';
    $isOnlyOneAddress = empty($billingAddress) || empty($shippingAddress);

    // Registering a new account sends an activation email; fake notifications so that send
    // doesn't need a real mailer/queue round-trip.
    Notification::fake();

    $order = createCustomerOrder($this->fixture, $email);
    $order->registerUserOnOrderComplete = true;
    Elements::saveElement($order, false);

    if (!empty($billingAddress)) {
        $order->setBillingAddress($billingAddress);
    }

    if (!empty($shippingAddress)) {
        $order->setShippingAddress($shippingAddress);
    }

    expect($order->markAsComplete())->toBeTrue();

    $customer = $order->getCustomer();
    $userAddresses = Address::find()->ownerId($customer->id)->all();
    expect($userAddresses)->toHaveCount($addressCount);

    $primaryCount = 0;
    foreach ($userAddresses as $userAddress) {
        if ($addressCount === 1) {
            $addressTitle = t('Address', category: 'app');
            if ($isOnlyOneAddress) {
                $addressTitle = !empty($billingAddress) ? t('Billing Address', category: 'commerce') : t('Shipping Address', category: 'commerce');
            }
            expect($userAddress->title)->toBe($addressTitle);

            $address = $billingAddress ?? $shippingAddress;
            expect($userAddress->fullName)->toBe($address['fullName']);
            expect($userAddress->addressLine1)->toBe($address['addressLine1']);
            expect($userAddress->locality)->toBe($address['locality']);
            expect($userAddress->administrativeArea)->toBe($address['administrativeArea']);
            expect($userAddress->postalCode)->toBe($address['postalCode']);
            expect($userAddress->countryCode)->toBe($address['countryCode']);
        }

        if ($userAddress->getIsPrimaryBilling()) {
            if ($addressCount === 2) {
                expect($userAddress->title)->toBe(t('Billing Address', category: 'commerce'));
                expect($userAddress->fullName)->toBe($billingAddress['fullName']);
                expect($userAddress->addressLine1)->toBe($billingAddress['addressLine1']);
                expect($userAddress->locality)->toBe($billingAddress['locality']);
                expect($userAddress->administrativeArea)->toBe($billingAddress['administrativeArea']);
                expect($userAddress->postalCode)->toBe($billingAddress['postalCode']);
                expect($userAddress->countryCode)->toBe($billingAddress['countryCode']);
            }

            $primaryCount++;
        }

        if ($userAddress->getIsPrimaryShipping()) {
            if ($addressCount === 2) {
                expect($userAddress->title)->toBe(t('Shipping Address', category: 'commerce'));
                expect($userAddress->fullName)->toBe($shippingAddress['fullName']);
                expect($userAddress->addressLine1)->toBe($shippingAddress['addressLine1']);
                expect($userAddress->locality)->toBe($shippingAddress['locality']);
                expect($userAddress->administrativeArea)->toBe($shippingAddress['administrativeArea']);
                expect($userAddress->postalCode)->toBe($shippingAddress['postalCode']);
                expect($userAddress->countryCode)->toBe($shippingAddress['countryCode']);
            }

            $primaryCount++;
        }
    }

    expect($primaryCount)->toBe($isOnlyOneAddress ? 1 : 2);
})->with([
    'guest-two-addresses' => [$guestBillingAddress, $guestShippingAddress, 2],
    'guest-matching-addresses' => [$guestBillingAddress, $guestBillingAddress, 1],
    'guest-one-billing-address' => [$guestBillingAddress, null, 1],
    'guest-one-shipping-address' => [null, $guestShippingAddress, 1],
]);

$savedBillingAddress = [
    'fullName' => 'Billing Name',
    'addressLine1' => '1 Main Billing Street',
    'locality' => 'Billingsville',
    'administrativeArea' => 'OR',
    'postalCode' => '12345',
    'countryCode' => 'US',
];
$savedShippingAddress = [
    'fullName' => 'Shipping Name',
    'addressLine1' => '1 Main Shipping Street',
    'locality' => 'Shippingsville',
    'administrativeArea' => 'AL',
    'postalCode' => '98765',
    'countryCode' => 'US',
];

test('orderCompleteHandler saves the order\'s addresses to a credentialed customer\'s address book only when requested and no source address is already set', function(?bool $saveBilling, ?array $billingAddress, ?bool $saveShipping, ?array $shippingAddress, int $newAddressCount, bool $setSourceBilling, bool $setSourceShipping) {
    $email = 'source.address.customer@crafttest.com';
    $customer = ensureCredentialedCustomer($email);

    $order = createCustomerOrder($this->fixture, $email);

    if ($setSourceBilling || $setSourceShipping) {
        $sourceAddress = new Address([
            'fullName' => 'Source Address',
            'addressLine1' => '1 Source Road',
            'locality' => 'Sourcington',
            'administrativeArea' => 'OR',
            'postalCode' => '991199',
            'countryCode' => 'US',
        ]);
        $sourceAddress->setPrimaryOwner($customer);
        $sourceAddress->setOwner($customer);

        if (!Elements::saveElement($sourceAddress, false, false, false)) {
            throw new RuntimeException('Could not save source address: ' . json_encode($sourceAddress->errors()->all()));
        }

        if ($setSourceBilling) {
            $order->sourceBillingAddressId = $sourceAddress->id;
        }

        if ($setSourceShipping) {
            $order->sourceShippingAddressId = $sourceAddress->id;
        }
    }

    $originalAddressIds = collect(Address::find()->ownerId($customer->id)->all())->pluck('id')->all();

    $order->saveBillingAddressOnOrderComplete = $saveBilling;
    $order->saveShippingAddressOnOrderComplete = $saveShipping;
    $order->setBillingAddress($billingAddress);
    $order->setShippingAddress($shippingAddress);

    Elements::saveElement($order, false, false, false);

    expect($order->markAsComplete())->toBeTrue();

    $addressQuery = Address::find()->ownerId($customer->id);
    if (!empty($originalAddressIds)) {
        $addressQuery->id(array_merge(['not'], $originalAddressIds));
    }

    $addresses = $addressQuery->all();
    expect($addresses)->toHaveCount($newAddressCount);

    $addressNames = collect($addresses)->pluck('fullName')->all();
    $addressLine1s = collect($addresses)->pluck('addressLine1')->all();

    if ($billingAddress && $saveBilling && !$setSourceBilling) {
        expect($addressNames)->toContain($billingAddress['fullName']);
        expect($addressLine1s)->toContain($billingAddress['addressLine1']);
    }

    if ($shippingAddress && $saveShipping && !$setSourceShipping) {
        expect($addressNames)->toContain($shippingAddress['fullName']);
        expect($addressLine1s)->toContain($shippingAddress['addressLine1']);
    }
})->with([
    'save-both' => [true, $savedBillingAddress, true, $savedShippingAddress, 2, false, false],
    'save-billing-only' => [true, $savedBillingAddress, false, null, 1, false, false],
    'save-shipping-only' => [false, null, true, $savedShippingAddress, 1, false, false],
    'save-both-but-same-address' => [true, $savedBillingAddress, true, $savedBillingAddress, 1, false, false],
    'try-to-save-both-but-no-addresses' => [true, null, true, null, 0, false, false],
    'try-to-save-but-source-billing-present' => [true, $savedBillingAddress, false, null, 0, true, false],
    'try-to-save-but-source-shipping-present' => [false, null, true, $savedShippingAddress, 0, false, true],
    'try-to-save-both-but-sources-present' => [true, $savedBillingAddress, true, $savedShippingAddress, 0, true, true],
    'try-save-both-but-billing-source-present' => [true, $savedBillingAddress, true, $savedShippingAddress, 1, true, false],
    'try-save-both-but-shipping-source-present' => [true, $savedBillingAddress, true, $savedShippingAddress, 1, false, true],
]);
