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
use craft\web\Request;
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

    /**
     * Tests that calling forgetCart() followed by getCart() in the same request returns a new
     * cart with a different number — verifying the fix in loadCookie() that respects the `false`
     * set by forgetCart() and prevents the cookie from restoring the forgotten cart.
     *
     * @see https://github.com/craftcms/commerce/issues/4279
     */
    public function testForgetCartPreventsCartRestoration(): void
    {
        $cartsService = Plugin::getInstance()->getCarts();

        // First call generates an in-memory cart number and returns a new cart.
        $initialCart = $cartsService->getCart();
        $originalNumber = $initialCart->number;

        // forgetCart() sets the private $_cartNumber sentinel to `false`.
        $cartsService->forgetCart();

        // A subsequent getCart() must generate a completely new number and return a fresh cart.
        $newCart = $cartsService->getCart();

        self::assertNotEquals(
            $originalNumber,
            $newCart->number,
            'After forgetCart(), getCart() should return a cart with a new number.',
        );
    }

    /**
     * Demonstrates that without the fix, a web request whose cookie still carries the forgotten
     * cart number causes getCart() to reuse that number — exactly as the old loadCookie() would
     * have behaved before the `$this->_cartNumber === false` guard was introduced.
     *
     * @see https://github.com/craftcms/commerce/issues/4279
     */
    public function testForgetCartWithRestoredCartNumberReturnsSameNumber(): void
    {
        $carts = Plugin::getInstance()->getCarts();

        // Get an initial cart and number.
        $initialCart = $carts->getCart();
        $originalNumber = $initialCart->number;

        // Forget the cart — $_cartNumber is now `false`.
        $carts->forgetCart();

        $cookieName = 'test_commerce_cart';
        $carts->cartCookie = ['name' => $cookieName];

        // Simulate the pre-fix state: set $_cartNumber to `null`.
        // In old code the `$this->_cartNumber === false` guard didn't exist, so even after
        // forgetCart() wrote `false`, loadCookie() would proceed and silently overwrite it
        // with whatever value was in the request cookie.
        $reflection = new \ReflectionClass($carts);
        $cartNumberProp = $reflection->getProperty('_cartNumber');
        $cartNumberProp->setAccessible(true);
        $cartNumberProp->setValue($carts, null);

        $requestCookies = new \yii\web\CookieCollection();
        $requestCookies->add(new \yii\web\Cookie([
            'name' => $cookieName,
            'value' => $originalNumber,
        ]));

        $originalRequest = \Craft::$app->getRequest();

        // Create a mock request class to return test data
        $requestMock = $this->make(Request::class, [
            'getIsConsoleRequest' => false,
            'getCookies' => $requestCookies,
        ]);

        Craft::$app->set('request', $requestMock);

        try {
            $restoredCart = $carts->getCart();

            self::assertEquals(
                $originalNumber,
                $restoredCart->number,
                'Without the false guard in loadCookie(), the request cookie restores the forgotten cart number.',
            );
        } finally {
            \Craft::$app->set('request', $originalRequest);
        }
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

    public function testGetStaticCartDoesNotStartCartSession(): void
    {
        $originalCarts = Plugin::getInstance()->getCarts();
        $cartNumber = $originalCarts->generateCartNumber();
        $cookieName = $originalCarts->cartCookie['name'];

        $order = new Order();
        $order->number = $cartNumber;
        Craft::$app->getElements()->saveElement($order, false);

        $carts = $this->make(Carts::class, [
            'setSessionCartNumber' => function() {
                self::fail('Static cart retrieval should not update the cart session.');
            },
        ]);
        $carts->cartCookie = ['name' => $cookieName];
        Plugin::getInstance()->set('carts', $carts);

        $requestCookies = new \yii\web\CookieCollection();
        $requestCookies->add(new \yii\web\Cookie([
            'name' => $cookieName,
            'value' => $cartNumber,
        ]));
        $originalRequest = Craft::$app->getRequest();
        $requestMock = $this->make(Request::class, [
            'getCookies' => $requestCookies,
        ]);
        Craft::$app->set('request', $requestMock);

        try {
            $cart = Plugin::getInstance()->getCarts()->getStaticCart();

            self::assertNotNull($cart);
            self::assertSame($cartNumber, $cart->number);
        } finally {
            Craft::$app->set('request', $originalRequest);
            Craft::$app->getElements()->deleteElement($order, true);
        }
    }

    public function testGetStaticCartReturnsNullWithNoCookie(): void
    {
        $cookieName = Plugin::getInstance()->getCarts()->cartCookie['name'];

        $carts = $this->make(Carts::class);
        $carts->cartCookie = ['name' => $cookieName];
        Plugin::getInstance()->set('carts', $carts);

        $originalRequest = Craft::$app->getRequest();
        $requestMock = $this->make(Request::class, [
            'getCookies' => new \yii\web\CookieCollection(),
        ]);
        Craft::$app->set('request', $requestMock);

        try {
            $cart = Plugin::getInstance()->getCarts()->getStaticCart();
            self::assertNull($cart);
        } finally {
            Craft::$app->set('request', $originalRequest);
        }
    }
}
