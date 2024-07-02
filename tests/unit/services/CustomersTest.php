<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\Plugin;
use craft\commerce\services\Customers;
use craft\elements\Address;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * CustomersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class CustomersTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var OrdersFixture
     */
    protected OrdersFixture $fixtureData;

    /**
     * @var array
     */
    private array $_deleteElementIds = [];

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'customer' => [
                'class' => CustomerFixture::class,
            ],
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    protected function _before(): void
    {
        parent::_before();

        $this->fixtureData = $this->tester->grabFixture('orders');
    }

    public function testOrderCompleteHandlerNotCalled(): void
    {
        Plugin::getInstance()->set('customers', $this->make(Customers::class, [
            'orderCompleteHandler' => function() {
                self::never();
            },
        ]));

        /** @var Order $completedOrder */
        $completedOrder = $this->fixtureData->getElement('completed-new');

        self::assertTrue($completedOrder->markAsComplete());
    }

    public function testOrderCompleteHandlerCalled(): void
    {
        Plugin::getInstance()->set('customers', $this->make(Customers::class, [
            'orderCompleteHandler' => function() {
                self::once();
            },
        ]));

        $order = $this->_createOrder('test@newemailaddress.xyz');

        self::assertTrue($order->markAsComplete());

        $this->_deleteElementIds[] = $order->id;
        $this->_deleteElementIds[] = $order->getCustomer()->id;
    }

    /**
     * @param string $email
     * @param bool $register
     * @param bool $deleteUser an argument to help with cleanup
     * @return void
     * @throws \Throwable
     * @throws OrderStatusException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @dataProvider registerOnCheckoutDataProvider
     */
    public function testRegisterOnCheckout(string $email, bool $register, bool $deleteUser): void
    {
        $order = $this->_createOrder($email);
        $originallyCredentialed = $order->getCustomer()->getIsCredentialed();

        $order->registerUserOnOrderComplete = $register;

        self::assertTrue($order->markAsComplete());

        $foundUser = User::find()->email($email)->status(null)->one();
        self::assertNotNull($foundUser);

        if ($register || $originallyCredentialed) {
            self::assertTrue($foundUser->getIsCredentialed());
        } else {
            self::assertFalse($foundUser->getIsCredentialed());
        }

        $this->_deleteElementIds[] = $order->id;
        if ($deleteUser) {
            $this->_deleteElementIds[] = $order->getCustomer()->id;
        }
    }

    /**
     * @return array[]
     */
    public function registerOnCheckoutDataProvider(): array
    {
        return [
            'dont-register-guest' => ['guest@crafttest.com', false, true],
            'register-guest' => ['guest@crafttest.com', true, true],
            'register-credentialed-user' => ['cred.user@crafttest.com', true, false],
            'dont-register-credentialed-user' => ['cred.user@crafttest.com', false, false],
        ];
    }

    /**
     * @param string $email
     * @param bool $deleteUser
     * @param Address|null $billingAddres
     * @param Address|null $shippingAddress
     * @return void
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws OrderStatusException
     * @throws \Throwable
     * @dataProvider registerOnCheckoutCopyAddressesDataProvider
     */
    public function testRegisterOnCheckoutCopyAddresses(string $email, ?array $billingAddress, ?array $shippingAddress, int $addressCount): void
    {
        $isOnlyOneAddress = empty($billingAddress) || empty($shippingAddress);
        $order = $this->_createOrder($email);
        $order->registerUserOnOrderComplete = true;
        \Craft::$app->getElements()->saveElement($order, false);

        if (!empty($billingAddress)) {
            $order->setBillingAddress($billingAddress);
        }

        if (!empty($shippingAddress)) {
            $order->setShippingAddress($shippingAddress);
        }

        self::assertTrue($order->markAsComplete());

        $userAddresses = Address::find()->ownerId($order->getCustomer()->id)->all();
        self::assertCount($addressCount, $userAddresses);

        $primaryCount = 0;
        foreach ($userAddresses as $userAddress) {
            if ($addressCount === 1) {
                $addressTitle = \Craft::t('commerce', 'Address');
                if ($isOnlyOneAddress) {
                    $addressTitle = !empty($billingAddress) ? \Craft::t('commerce', 'Billing Address') : \Craft::t('commerce', 'Shipping Address');
                }
                self::assertEquals($addressTitle, $userAddress->title);

                $address = $billingAddress ?? $shippingAddress;
                self::assertEquals($address['fullName'], $userAddress->fullName);
                self::assertEquals($address['addressLine1'], $userAddress->addressLine1);
                self::assertEquals($address['locality'], $userAddress->locality);
                self::assertEquals($address['administrativeArea'], $userAddress->administrativeArea);
                self::assertEquals($address['postalCode'], $userAddress->postalCode);
                self::assertEquals($address['countryCode'], $userAddress->countryCode);
            }

            if ($userAddress->getIsPrimaryBilling()) {
                if ($addressCount === 2) {
                    self::assertEquals(\Craft::t('commerce', 'Billing Address'), $userAddress->title);
                    self::assertEquals($billingAddress['fullName'], $userAddress->fullName);
                    self::assertEquals($billingAddress['addressLine1'], $userAddress->addressLine1);
                    self::assertEquals($billingAddress['locality'], $userAddress->locality);
                    self::assertEquals($billingAddress['administrativeArea'], $userAddress->administrativeArea);
                    self::assertEquals($billingAddress['postalCode'], $userAddress->postalCode);
                    self::assertEquals($billingAddress['countryCode'], $userAddress->countryCode);
                }

                $primaryCount++;
            }
            if ($userAddress->getIsPrimaryShipping()) {
                if ($addressCount === 2) {
                    self::assertEquals(\Craft::t('commerce', 'Shipping Address'), $userAddress->title);
                    self::assertEquals($shippingAddress['fullName'], $userAddress->fullName);
                    self::assertEquals($shippingAddress['addressLine1'], $userAddress->addressLine1);
                    self::assertEquals($shippingAddress['locality'], $userAddress->locality);
                    self::assertEquals($shippingAddress['administrativeArea'], $userAddress->administrativeArea);
                    self::assertEquals($shippingAddress['postalCode'], $userAddress->postalCode);
                    self::assertEquals($shippingAddress['countryCode'], $userAddress->countryCode);
                }

                $primaryCount++;
            }
        }

        self::assertEquals($isOnlyOneAddress ? 1 : 2, $primaryCount);

        $this->_deleteElementIds[] = $order->id;
        $this->_deleteElementIds[] = $order->getCustomer()->id;
    }

    /**
     * @return array[]
     */
    public function registerOnCheckoutCopyAddressesDataProvider(): array
    {
        $billingAddress = [
            'fullName' => 'Guest Billing',
            'addressLine1' => '1 Main Billing Street',
            'locality' => 'Billingsville',
            'administrativeArea' => 'OR',
            'postalCode' => '12345',
            'countryCode' => 'US',
        ];
        $shippingAddress = [
            'fullName' => 'Guest Shipping',
            'addressLine1' => '1 Main Shipping Street',
            'locality' => 'Shippingsville',
            'administrativeArea' => 'AL',
            'postalCode' => '98765',
            'countryCode' => 'US',
        ];

        return [
            'guest-two-addresses' => [
                'guest.person@crafttest.com',
                $billingAddress,
                $shippingAddress,
                2,
            ],
            'guest-matching-addresses' => [
                'guest.person@crafttest.com',
                $billingAddress,
                $billingAddress,
                1,
            ],
            'guest-one-billing-address' => [
                'guest.person@crafttest.com',
                $billingAddress,
                null,
                1,
            ],
            'guest-one-shipping-address' => [
                'guest.person@crafttest.com',
                null,
                $shippingAddress,
                1,
            ],
        ];
    }

    /**
     * @param bool|null $saveBilling
     * @param array|null $billingAddress
     * @param bool|null $saveShipping
     * @param array|null $shippingAddress
     * @param bool $setSourceBilling
     * @param bool $setSourceShipping
     * @return void
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws OrderStatusException
     * @throws \Throwable
     * @dataProvider saveAddressesOnOrderCompleteDataProvider
     * @since 4.3.0
     */
    public function testSaveAddressesOnOrderComplete(?bool $saveBilling, ?array $billingAddress, ?bool $saveShipping, ?array $shippingAddress, int $newAddressCount, bool $setSourceBilling, bool $setSourceShipping): void
    {
        $order = $this->_createOrder('cred.user@crafttest.com');
        $customer = $order->getCustomer();
        $sourceAddress = [
            'fullName' => 'Source Address',
            'addressLine1' => '1 Source Road',
            'locality' => 'Sourcington',
            'administrativeArea' => 'OR',
            'postalCode' => '991199',
            'countryCode' => 'US',
            'ownerId' => $customer->id,
        ];

        if ($setSourceBilling || $setSourceShipping) {
            $sourceAddressModel = \Craft::createObject([
                'class' => Address::class,
                'attributes' => $sourceAddress,
            ]);
            \Craft::$app->getElements()->saveElement($sourceAddressModel, false, false ,false);
            $this->_deleteElementIds[] = $sourceAddressModel->id;

            if ($setSourceBilling) {
                $order->sourceBillingAddressId = $sourceAddressModel->id;
            }

            if ($setSourceShipping) {
                $order->sourceShippingAddressId = $sourceAddressModel->id;
            }
        }
        $originalAddressIds = collect($customer->getAddresses())->pluck('id')->all();

        $order->saveBillingAddressOnOrderComplete = $saveBilling;
        $order->saveShippingAddressOnOrderComplete = $saveShipping;

        $order->setBillingAddress($billingAddress);
        $order->setShippingAddress($shippingAddress);

        \Craft::$app->getElements()->saveElement($order, false, false, false);

        // Get the ID in early to delete in case of failure
        $this->_deleteElementIds[] = $order->id;

        self::assertTrue($order->markAsComplete());

        // @TODO change this to `$customer->getAddresses()` when `getAddresses()` memoization is fixed
        $addressQuery = Address::find()->ownerId($customer->id);
        // @TODO update this to use `primaryOwnerId` when `primaryOwnerId` query param is fixed
        // $addressQuery = Address::find()->primaryOwnerId($customer->id);

        if (!empty($originalAddressIds)) {
            $addressQuery->id(array_merge(['not'], $originalAddressIds));
        }

        $addresses = $addressQuery->all();
        self::assertCount($newAddressCount, $addresses);
        $addressNames = collect($addresses)->pluck('fullName')->all();
        $addressLine1s = collect($addresses)->pluck('addressLine1')->all();

        if ($billingAddress && $saveBilling && !$setSourceBilling) {
            self::assertContains($billingAddress['fullName'], $addressNames);
            self::assertContains($billingAddress['addressLine1'], $addressLine1s);
        }

        if ($shippingAddress && $saveShipping && !$setSourceShipping) {
            self::assertContains($shippingAddress['fullName'], $addressNames);
            self::assertContains($shippingAddress['addressLine1'], $addressLine1s);
        }
    }

    /**
     * @return array
     */
    public function saveAddressesOnOrderCompleteDataProvider(): array
    {
        $billingAddress = [
            'fullName' => 'Billing Name',
            'addressLine1' => '1 Main Billing Street',
            'locality' => 'Billingsville',
            'administrativeArea' => 'OR',
            'postalCode' => '12345',
            'countryCode' => 'US',
        ];
        $shippingAddress = [
            'fullName' => 'Shipping Name',
            'addressLine1' => '1 Main Shipping Street',
            'locality' => 'Shippingsville',
            'administrativeArea' => 'AL',
            'postalCode' => '98765',
            'countryCode' => 'US',
        ];

        return [
            'save-both' => [
                true, // save billing
                $billingAddress, // billing address
                true, // save shipping
                $shippingAddress, // shipping address
                2, // new address count
                false, // set source billing
                false, // set source shipping
            ],
            'save-billing-only' => [
                true,
                $billingAddress,
                false,
                null,
                1,
                false,
                false,
            ],
            'save-shipping-only' => [
                false,
                null,
                true,
                $shippingAddress,
                1,
                false,
                false,
            ],
            'save-both-but-same-address' => [
                true,
                $billingAddress,
                true,
                $billingAddress,
                1,
                false,
                false,
            ],
            'try-to-save-both-but-no-addresses' => [
                true,
                null,
                true,
                null,
                0,
                false,
                false,
            ],
            'try-to-save-but-source-billing-present' => [
                true,
                $billingAddress,
                false,
                null,
                0,
                true,
                false,
            ],
            'try-to-save-but-source-shipping-present' => [
                false,
                null,
                true,
                $shippingAddress,
                0,
                false,
                true,
            ],
            'try-to-save-both-but-sources-present' => [
                true,
                $billingAddress,
                true,
                $shippingAddress,
                0,
                true,
                true,
            ],
            'try-save-both-but-billing-source-present' => [
                true,
                $billingAddress,
                true,
                $shippingAddress,
                1,
                true,
                false,
            ],
            'try-save-both-but-shipping-source-present' => [
                true,
                $billingAddress,
                true,
                $shippingAddress,
                1,
                false,
                true,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();

        // Cleanup data.
        foreach ($this->_deleteElementIds as $elementId) {
            \Craft::$app->getElements()->deleteElementById($elementId, null, null, true);
        }
    }

    private function _createOrder(string $email): Order
    {
        $order = new Order();
        $user = \Craft::$app->getUsers()->ensureUserByEmail($email);
        $order->setCustomer($user);

        $completedOrder = $this->fixtureData->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = Plugin::getInstance()->getLineItems()->create($order, [
            'purchasableId' => $lineItem->purchasableId,
            'options' => [],
            'qty' => $qty,
            'note' => $note,
        ]);
        $order->setLineItems([$lineItem]);

        return $order;
    }
}
