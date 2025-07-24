<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\services\Carts;
use craft\commerce\services\Stores;
use craftcommercetests\fixtures\CustomerAddressFixture;
use craftcommercetests\fixtures\CustomerFixture;
use UnitTester;

/**
 * CartsTest.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.2
 */
class CartsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    public function _fixtures(): array
    {
        return [
            'customer' => [
                'class' => CustomerFixture::class,
            ],
            'customerAddresses' => [
                'class' => CustomerAddressFixture::class,
            ],
        ];
    }

    /**
     * @param string $email
     * @param bool $autoSet
     * @param bool $hasBillingAddress
     * @param bool $hasShippingAddress
     * @return void
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\UnknownPropertyException
     * @dataProvider getCartDataProvider
     */
    public function testGetCartAutoSetAddresses(string $email, bool $autoSet, bool $hasBillingAddress, bool $hasShippingAddress, bool $loggedIn): void
    {
        $cartNumber = Plugin::getInstance()->getCarts()->generateCartNumber();

        $store = Plugin::getInstance()->getStores()->getCurrentStore();
        Plugin::getInstance()->set('stores', $this->make(Stores::class, [
            'getStoreById' => function(int $id) use ($autoSet, $store) {
                $store->setAutoSetNewCartAddresses($autoSet);
                return $store;
            },
        ]));

        Plugin::getInstance()->set('carts', $this->make(Carts::class, [
            'getSessionCartNumber' => fn() => $cartNumber,
        ]));

        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        if ($loggedIn) {
            Craft::$app->getUser()->setIdentity($user);
            Craft::$app->getUser()->getIdentity()->password = $user->password;
        }

        $newCart = new Order();
        $newCart->setCustomer($user);
        $newCart->number = $cartNumber;
        Craft::$app->getElements()->saveElement($newCart, false);

        $cart = Plugin::getInstance()->getCarts()->getCart();

        if ($hasBillingAddress) {
            self::assertNotNull($cart->getBillingAddress());
        } else {
            self::assertNull($cart->getBillingAddress());
        }
        if ($hasShippingAddress) {
            self::assertNotNull($cart->getShippingAddress());
        } else {
            self::assertNull($cart->getShippingAddress());
        }

        Craft::$app->getElements()->deleteElement($newCart, true);
    }

    public function getCartDataProvider(): array
    {
        return [
            'inactive-user-no-auto-set-addresses' => ['inactive.user@crafttest.com', false, false, false, false],
            'inactive-user-auto-set-addresses' => ['inactive.user@crafttest.com', true, false, false, false],
            'logged-in-user-no-auto-set-addresses' => ['cred.user@crafttest.com', false, false, false, true],
            'logged-in-user-auto-set-addresses' => ['cred.user@crafttest.com', true, true, true, true],
        ];
    }

    public function testGetCartSwitchCustomer(): void
    {
        $cartNumber = Plugin::getInstance()->getCarts()->generateCartNumber();
        Plugin::getInstance()->set('carts', $this->make(Carts::class, [
            'getSessionCartNumber' => fn() => $cartNumber,
        ]));

        $inactiveUser = $this->tester->grabFixture('customer')->getElement('inactive-user');
        $credUser = $this->tester->grabFixture('customer')->getElement('credentialed-user');
        $originalIdentity = Craft::$app->getUser()->getIdentity();
        Craft::$app->getUser()->setIdentity($credUser);
        Craft::$app->getUser()->getIdentity()->password = $credUser->password;


        $order = new Order();
        $order->number = $cartNumber;
        $order->setCustomer($inactiveUser);

        Craft::$app->getElements()->saveElement($order, false);
        self::assertEquals($inactiveUser->id, $order->getCustomerId());

        $cart = Plugin::getInstance()->getCarts()->getCart();

        // assert customer has changed;
        self::assertNotEquals($inactiveUser->id, $cart->getCustomerId());
        self::assertEquals($credUser->id, $cart->getCustomerId());
        self::assertEquals($credUser->email, $cart->getEmail());

        // Reset data
        Craft::$app->getUser()->setIdentity($originalIdentity);
        Craft::$app->getElements()->deleteElement($cart, true);
    }
}
