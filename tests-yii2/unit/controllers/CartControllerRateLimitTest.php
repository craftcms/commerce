<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace crafttests\unit\controllers;

use Craft;
use craft\commerce\controllers\CartController;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\filters\IpRateLimitIdentity;
use craft\test\TestCase;
use craft\web\Request;
use craftcommercetests\fixtures\ProductFixture;
use yii\base\Action;
use yii\web\Controller;
use yii\web\TooManyRequestsHttpException;

/**
 * Unit tests for CartControllerRateLimitTest.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.11.0
 */
class CartControllerRateLimitTest extends TestCase
{
    private IpRateLimitIdentity $identity;
    private Action $action;
    private Request $request;

    /**
     * @return array
     */
    #[\Override]
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Craft::$app->getCache()->flush();

        $this->identity = new IpRateLimitIdentity([
            'limit' => 3,
            'window' => 10,
            'ip' => '192.168.1.1',
            'keyPrefix' => 'cart-rate-limit',
        ]);

        $controller = $this->createMock(Controller::class);
        $this->action = new Action('test-action', $controller);
        $this->request = Craft::$app->getRequest();
    }

    /**
     * @return void
     */
    public function testGetRateLimit(): void
    {
        [$limit, $window] = $this->identity->getRateLimit($this->request, $this->action);
        self::assertSame(3, $limit);
        self::assertSame(10, $window);
    }

    /**
     * @return void
     */
    public function testLoadAllowanceReturnsDefaultWhenCacheEmpty(): void
    {
        [$allowance, $timestamp] = $this->identity->loadAllowance($this->request, $this->action);
        self::assertSame(3, $allowance);
        self::assertEqualsWithDelta(time(), $timestamp, 1);
    }

    /**
     * @return void
     */
    public function testSaveAndLoadAllowance(): void
    {
        $this->identity->saveAllowance($this->request, $this->action, 1, 1000000);

        [$allowance, $timestamp] = $this->identity->loadAllowance($this->request, $this->action);
        self::assertSame(1, $allowance);
        self::assertSame(1000000, $timestamp);
    }

    /**
     * @return void
     */
    public function testDifferentIpsGetIndependentAllowances(): void
    {
        // Save allowance for first IP
        $this->identity->saveAllowance($this->request, $this->action, 0, 1000000);

        // Create identity with different IP
        $otherIdentity = new IpRateLimitIdentity([
            'limit' => 3,
            'window' => 10,
            'ip' => '10.0.0.1',
            'keyPrefix' => 'cart-rate-limit',
        ]);

        // Second IP should still have full allowance (cache miss = default)
        [$allowance, $timestamp] = $otherIdentity->loadAllowance($this->request, $this->action);
        self::assertSame(3, $allowance);
        self::assertEqualsWithDelta(time(), $timestamp, 1);

        // First IP should still be exhausted
        [$allowance] = $this->identity->loadAllowance($this->request, $this->action);
        self::assertSame(0, $allowance);
    }

    public function testMultipleRequests(): void
    {
        $request = Craft::$app->getRequest();
        $request->enableCsrfValidation = false;
        $cartController = new CartController('cart', Plugin::getInstance());

        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Http-Method-Override', 'POST');

        // Create a cart to get a cart number
        $variant = Variant::find()->sku('rad-hood')->one();
        $bodyParams = [
            'purchasableId' => $variant->id,
            'qty' => 1,
        ];
        $request->setBodyParams($bodyParams);

        // Refresh CartController to ensure the request is properly initialized with the new body params
        $cartController = new CartController('cart', Plugin::getInstance());

        $cartController->runAction('update-cart');
        $cart = Plugin::getInstance()->getCarts()->getCart();

        // First request with `number` should succeed
        $bodyParams['number'] = $cart->number;
        $bodyParams['qty'] += 1;

        $cartController = new CartController('cart', Plugin::getInstance());

        $request->setBodyParams($bodyParams);
        $result = $cartController->runAction('update-cart');

        self::assertSame(200, $result->getStatusCode());

        $cartController = new CartController('cart', Plugin::getInstance());

        // Second request with same `number` should fail with 429 Too Many Requests
        $request->setBodyParams($bodyParams);

        try {
            $result = $cartController->runAction('update-cart');
        } catch (TooManyRequestsHttpException $e) {
            self::assertSame(429, $e->statusCode);
        }

        if ($cart->id) {
            Craft::$app->getElements()->deleteElement($cart, true);
        }
    }
}
