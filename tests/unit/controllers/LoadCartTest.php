<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\CartController;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\web\Request;
use craftcommercetests\fixtures\CustomerFixture;
use Throwable;
use UnitTester;
use yii\base\InvalidRouteException;
use yii\web\Response;

/**
 * LoadCartTest
 *
 * Tests for loading carts via the cart controller.
 *
 * Test scenarios:
 * - CART-A: Cart belonging to a customer with an email for a non-credentialed user (inactive user)
 * - CART-B: Cart with no email (no customer)
 * - CART-C: Cart with an email (customer) of a credentialed user
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.x
 */
class LoadCartTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var CartController
     */
    protected CartController $cartController;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var Order|null CART-A: Cart for inactive (non-credentialed) user
     */
    private ?Order $_cartA = null;

    /**
     * @var Order|null CART-B: Cart with no customer
     */
    private ?Order $_cartB = null;

    /**
     * @var Order|null CART-C: Cart for credentialed user
     */
    private ?Order $_cartC = null;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'customer' => [
                'class' => CustomerFixture::class,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->cartController = new CartController('cart', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;

        // Create the test carts
        $this->_createTestCarts();
    }

    /**
     * @inheritDoc
     */
    protected function _after(): void
    {
        parent::_after();

        // Clean up test carts
        $this->_cleanupTestCarts();

        // Forget any cart in session
        Plugin::getInstance()->getCarts()->forgetCart();
    }

    /**
     * Create test carts for the scenarios.
     */
    private function _createTestCarts(): void
    {
        $customerFixture = $this->tester->grabFixture('customer');
        $store = Plugin::getInstance()->getStores()->getCurrentStore();

        // CART-A: Cart for inactive (non-credentialed) user
        /** @var User $inactiveUser */
        $inactiveUser = $customerFixture->getElement('inactive-user');
        $this->_cartA = new Order();
        $this->_cartA->number = Plugin::getInstance()->getCarts()->generateCartNumber();
        $this->_cartA->storeId = $store->id;
        $this->_cartA->setCustomer($inactiveUser);
        Craft::$app->getElements()->saveElement($this->_cartA, false);

        // CART-B: Cart with no customer (no email)
        $this->_cartB = new Order();
        $this->_cartB->number = Plugin::getInstance()->getCarts()->generateCartNumber();
        $this->_cartB->storeId = $store->id;
        Craft::$app->getElements()->saveElement($this->_cartB, false);

        // CART-C: Cart for credentialed user
        /** @var User $credentialedUser */
        $credentialedUser = $customerFixture->getElement('credentialed-user');
        $this->_cartC = new Order();
        $this->_cartC->number = Plugin::getInstance()->getCarts()->generateCartNumber();
        $this->_cartC->storeId = $store->id;
        $this->_cartC->setCustomer($credentialedUser);
        Craft::$app->getElements()->saveElement($this->_cartC, false);
    }

    /**
     * Clean up test carts.
     */
    private function _cleanupTestCarts(): void
    {
        foreach ([$this->_cartA, $this->_cartB, $this->_cartC] as $cart) {
            if ($cart && $cart->id) {
                try {
                    Craft::$app->getElements()->deleteElement($cart, true);
                } catch (Throwable) {
                    // Ignore cleanup errors
                }
            }
        }

        $this->_cartA = null;
        $this->_cartB = null;
        $this->_cartC = null;
    }

    /**
     * Test that an anonymous user can load a cart with no customer (CART-B).
     *
     * @throws InvalidRouteException
     */
    public function testAnonymousUserCanLoadCartWithNoCustomer(): void
    {
        Plugin::getInstance()->getCarts()->forgetCart();

        $this->request->headers->set('Accept', 'application/json');
        $this->request->setQueryParams([
            'number' => $this->_cartB->number,
        ]);

        $response = $this->cartController->runAction('load-cart');

        self::assertInstanceOf(Response::class, $response);
        self::assertTrue($response->getIsSuccessful(), 'Anonymous user should be able to load cart with no customer');

        // Verify the cart exists and can be retrieved by number
        $loadedCart = Order::find()->number($this->_cartB->number)->isCompleted(false)->one();
        self::assertNotNull($loadedCart, 'CART-B should exist and be retrievable');
        self::assertSame($this->_cartB->id, $loadedCart->id, 'Loaded cart ID should match CART-B');
        self::assertNull($loadedCart->getEmail(), 'CART-B should have no email');
    }

    /**
     * Test that an anonymous user can load a cart belonging to a non-credentialed (inactive) user (CART-A).
     *
     * @throws InvalidRouteException
     */
    public function testAnonymousUserCanLoadCartWithInactiveUser(): void
    {
        Plugin::getInstance()->getCarts()->forgetCart();

        $this->request->headers->set('Accept', 'application/json');
        $this->request->setQueryParams([
            'number' => $this->_cartA->number,
        ]);

        $response = $this->cartController->runAction('load-cart');

        self::assertInstanceOf(Response::class, $response);
        self::assertTrue($response->getIsSuccessful(), 'Anonymous user should be able to load cart belonging to inactive user');

        // Verify the cart exists and has the inactive user's email
        $loadedCart = Order::find()->number($this->_cartA->number)->isCompleted(false)->one();
        self::assertNotNull($loadedCart, 'CART-A should exist and be retrievable');
        self::assertSame($this->_cartA->id, $loadedCart->id, 'Loaded cart ID should match CART-A');
        self::assertSame($this->_cartA->getEmail(), $loadedCart->getEmail(), 'Loaded cart should have inactive user email');

        // Verify the cart customer is NOT credentialed (is inactive)
        $cartCustomer = $loadedCart->getCustomer();
        self::assertNotNull($cartCustomer, 'CART-A should have a customer');
        self::assertFalse($cartCustomer->getIsCredentialed(), 'CART-A customer should not be credentialed');
    }

    /**
     * Test that an anonymous user cannot load a cart belonging to a credentialed user (CART-C).
     *
     * @throws InvalidRouteException
     */
    public function testAnonymousUserCannotLoadCartWithCredentialedUser(): void
    {
        Plugin::getInstance()->getCarts()->forgetCart();

        $this->request->headers->set('Accept', 'application/json');
        $this->request->setQueryParams([
            'number' => $this->_cartC->number,
        ]);

        $response = $this->cartController->runAction('load-cart');

        self::assertInstanceOf(Response::class, $response);
        self::assertFalse($response->getIsSuccessful(), 'Anonymous user should not be able to load cart belonging to credentialed user');

        // Verify CART-C still exists and belongs to the credentialed user (wasn't modified)
        $cartC = Order::find()->number($this->_cartC->number)->isCompleted(false)->one();
        self::assertNotNull($cartC, 'CART-C should still exist');
        self::assertSame($this->_cartC->id, $cartC->id, 'CART-C should be unchanged');

        // Verify the cart customer IS credentialed
        $cartCustomer = $cartC->getCustomer();
        self::assertNotNull($cartCustomer, 'CART-C should have a customer');
        self::assertTrue($cartCustomer->getIsCredentialed(), 'CART-C customer should be credentialed');
    }

    /**
     * Test that a credentialed user can load their own cart (CART-C).
     *
     * @throws InvalidRouteException
     */
    public function testCredentialedUserCanLoadOwnCart(): void
    {
        // Log in as the credentialed user
        $customerFixture = $this->tester->grabFixture('customer');
        /** @var User $credentialedUser */
        $credentialedUser = $customerFixture->getElement('credentialed-user');
        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserById($credentialedUser->id)
        );

        Plugin::getInstance()->getCarts()->forgetCart();

        $this->request->headers->set('Accept', 'application/json');
        $this->request->setQueryParams([
            'number' => $this->_cartC->number,
        ]);

        $response = $this->cartController->runAction('load-cart');

        self::assertInstanceOf(Response::class, $response);
        self::assertTrue($response->getIsSuccessful(), 'Credentialed user should be able to load their own cart');

        // Verify the cart exists and belongs to the credentialed user
        $loadedCart = Order::find()->number($this->_cartC->number)->isCompleted(false)->one();
        self::assertNotNull($loadedCart, 'CART-C should exist and be retrievable');
        self::assertSame($this->_cartC->id, $loadedCart->id, 'Loaded cart ID should match CART-C');
        self::assertSame($credentialedUser->id, $loadedCart->getCustomerId(), 'Loaded cart should belong to the credentialed user');
        self::assertSame($credentialedUser->email, $loadedCart->getEmail(), 'Loaded cart email should match credentialed user email');

        // Verify the cart customer IS credentialed
        $cartCustomer = $loadedCart->getCustomer();
        self::assertNotNull($cartCustomer, 'CART-C should have a customer');
        self::assertTrue($cartCustomer->getIsCredentialed(), 'CART-C customer should be credentialed');
    }
}
