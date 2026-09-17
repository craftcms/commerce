<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Customers;
use RuntimeException;

/**
 * Builds three customers for cart-ownership tests: an uncredentialed customer (inactive, no
 * password — the kind of `User` record a guest checkout gets), a credentialed customer with a
 * saved primary billing/shipping address, and a second credentialed customer with no addresses,
 * used as the "logs in and acquires someone else's cart" actor.
 */
class CartsFixture
{
    public User $inactiveUser;

    public User $credentialedUser;

    public User $loadingUser;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $inactiveUser = new User();
        $inactiveUser->username = 'cart-inactive-user';
        $inactiveUser->email = 'cart-inactive-user@crafttest.com';
        $inactiveUser->firstName = 'Inactive';
        $inactiveUser->lastName = 'User';
        $inactiveUser->active = false;
        if (!Elements::saveElement($inactiveUser)) {
            throw new RuntimeException('Could not save inactive user: ' . json_encode($inactiveUser->errors()->all()));
        }
        $this->inactiveUser = $inactiveUser;
        $this->savePrimaryAddress($inactiveUser);

        $credentialedUser = new User();
        $credentialedUser->username = 'cart-credentialed-user';
        $credentialedUser->email = 'cart-credentialed-user@crafttest.com';
        $credentialedUser->firstName = 'Credentialed';
        $credentialedUser->lastName = 'User';
        $credentialedUser->active = true;
        if (!Elements::saveElement($credentialedUser)) {
            throw new RuntimeException('Could not save credentialed user: ' . json_encode($credentialedUser->errors()->all()));
        }
        $this->credentialedUser = $credentialedUser;
        $this->savePrimaryAddress($credentialedUser);

        $loadingUser = new User();
        $loadingUser->username = 'cart-loading-user';
        $loadingUser->email = 'cart-loading-user@crafttest.com';
        $loadingUser->firstName = 'Loading';
        $loadingUser->lastName = 'User';
        $loadingUser->active = true;
        if (!Elements::saveElement($loadingUser)) {
            throw new RuntimeException('Could not save loading user: ' . json_encode($loadingUser->errors()->all()));
        }
        $this->loadingUser = $loadingUser;
    }

    private function savePrimaryAddress(User $user): void
    {
        $address = new Address();
        $address->setPrimaryOwner($user);
        $address->title = $user->firstName . ' ' . $user->lastName;
        $address->firstName = $user->firstName;
        $address->lastName = $user->lastName;
        $address->addressLine1 = '23 Woodworth';
        $address->locality = 'County Island';
        $address->postalCode = '12345';
        $address->countryCode = 'US';
        $address->administrativeArea = 'NY';
        if (!Elements::saveElement($address)) {
            throw new RuntimeException('Could not save address: ' . json_encode($address->errors()->all()));
        }

        app(Customers::class)->savePrimaryShippingAddressId($user, $address->id);
        app(Customers::class)->savePrimaryBillingAddressId($user, $address->id);
    }
}
