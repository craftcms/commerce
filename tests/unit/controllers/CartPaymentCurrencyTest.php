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
use craft\web\Request;
use craftcommercetests\fixtures\PaymentCurrenciesFixture;
use craftcommercetests\fixtures\StoreFixture;
use yii\web\BadRequestHttpException;

class CartPaymentCurrencyTest extends Unit
{
    protected CartController $controller;
    protected Request $request;

    public function _fixtures(): array
    {
        return [
            'stores' => StoreFixture::class,
            'payment-currencies' => PaymentCurrenciesFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();

        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $this->controller = new CartController('cart', Plugin::getInstance());
    }

    /**
     * @dataProvider unsupportedPaymentCurrencyDataProvider
     */
    public function testRejectsUnsupportedPaymentCurrencyBeforeUpdatingCart(mixed $currency): void
    {
        $cart = Plugin::getInstance()->getCarts()->getCart();
        $this->request->setBodyParams([
            'paymentCurrency' => $currency,
            'email' => 'changed@example.com',
        ]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported payment currency.');

        try {
            $this->controller->runAction('update-cart');
        } finally {
            self::assertNull($cart->id);
            self::assertSame('USD', $cart->paymentCurrency);
            self::assertNull($cart->email);
        }
    }

    public function unsupportedPaymentCurrencyDataProvider(): array
    {
        return [
            'cryptocurrency' => ['BTC'],
            'unconfigured ISO currency' => ['GBP'],
            'unknown currency' => ['INVALID'],
            'array' => [['USD']],
            'empty array' => [[]],
            'number' => [123],
            'boolean' => [false],
        ];
    }

    /**
     * @dataProvider supportedPaymentCurrencyDataProvider
     */
    public function testSupportedAndEmptyPaymentCurrencies(array $params, string $expectedCurrency): void
    {
        $cart = Plugin::getInstance()->getCarts()->getCart();
        $cart->paymentCurrency = 'AUD';
        $this->request->setBodyParams($params);

        $response = $this->controller->runAction('update-cart');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expectedCurrency, $response->data['cart']['paymentCurrency']);
        self::assertSame($expectedCurrency, Order::find()->id($cart->id)->one()->paymentCurrency);
    }

    public function supportedPaymentCurrencyDataProvider(): array
    {
        return [
            'omitted' => [[], 'AUD'],
            'null' => [['paymentCurrency' => null], 'AUD'],
            'empty string' => [['paymentCurrency' => ''], 'AUD'],
            'primary currency' => [['paymentCurrency' => 'USD'], 'USD'],
            'additional currency' => [['paymentCurrency' => 'EUR'], 'EUR'],
        ];
    }

    public function testRejectsCurrencyOnlyConfiguredForAnotherStore(): void
    {
        $plugin = Plugin::getInstance();
        $store = $plugin->getStores()->getStoreByHandle('ukStore');
        self::assertNotNull($plugin->getPaymentCurrencies()->getPaymentCurrencyByIso('EUR'));
        self::assertNull($plugin->getPaymentCurrencies()->getPaymentCurrencyByIso('EUR', $store->id));

        $cart = new Order([
            'storeId' => $store->id,
            'number' => $plugin->getCarts()->generateCartNumber(),
        ]);
        self::assertTrue(Craft::$app->getElements()->saveElement($cart));
        self::assertSame($store->id, Order::find()->id($cart->id)->one()->storeId);
        $this->request->setBodyParams([
            'number' => $cart->number,
            'paymentCurrency' => 'EUR',
        ]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported payment currency.');

        $this->controller->runAction('update-cart');
    }
}
