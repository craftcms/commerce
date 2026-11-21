<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\errors\OrderNumberConflictException;
use craft\commerce\services\Carts;
use craft\db\Query;
use craft\events\ModelEvent;
use craft\web\Request;
use craftcommercetests\fixtures\CustomerFixture;
use UnitTester;
use yii\base\Event;
use yii\web\Cookie;
use yii\web\CookieCollection;

class CartCreationTest extends Unit
{
    protected UnitTester $tester;

    private Request $originalRequest;
    private string $number;

    public function _fixtures(): array
    {
        return ['customers' => CustomerFixture::class];
    }

    protected function _before(): void
    {
        $this->originalRequest = Craft::$app->getRequest();
        $carts = new Carts();
        $this->number = $carts->generateCartNumber();

        $cookies = new CookieCollection();
        $cookies->add(new Cookie([
            'name' => $carts->cartCookie['name'],
            'value' => $this->number,
        ]));
        Craft::$app->set('request', $this->make(Request::class, [
            'getIsConsoleRequest' => false,
            'getCookies' => $cookies,
        ]));
    }

    protected function _after(): void
    {
        Craft::$app->set('request', $this->originalRequest);
    }

    public function testConcurrentForceSavesReturnTheSameCart(): void
    {
        $firstRequest = new Carts();
        $secondRequest = new Carts();
        $number = $this->number;
        $winner = null;

        // Let the other request persist the cart after this request has decided to insert it.
        $saveFirst = static function(ModelEvent $event) use ($secondRequest, $number, &$winner): void {
            if ($event->isNew && $event->sender->number === $number && $winner === null) {
                $winner = $secondRequest->getCart();
                self::assertNull($winner->id);
                $winner = $secondRequest->getCart(true);
            }
        };
        Event::on(Order::class, Order::EVENT_BEFORE_SAVE, $saveFirst);

        try {
            $cart = $firstRequest->getCart(true);
        } finally {
            Event::off(Order::class, Order::EVENT_BEFORE_SAVE, $saveFirst);
        }

        self::assertNotNull($winner?->id);
        self::assertSame($winner->id, $cart->id);
        self::assertSame($number, $cart->number);
        self::assertSame($cart, $firstRequest->getCart());
        self::assertEquals(1, (new Query())->from(Table::ORDERS)->where(['number' => $number])->count());
    }

    public function testForceSaveRecoversFromACartSavedDirectlyByAnotherRequest(): void
    {
        $carts = new Carts();
        $stale = $carts->getCart();
        self::assertNull($stale->id);

        $otherRequest = new Carts();
        $winner = $otherRequest->getCart();
        self::assertNull($winner->id);
        $winner->message = 'Saved by the other request';
        self::assertTrue(Craft::$app->getElements()->saveElement($winner, false));

        $cart = $carts->getCart(true);

        self::assertSame($winner->id, $cart->id);
        self::assertSame($winner->message, $cart->message);
        self::assertNotSame($stale, $cart);
    }

    public function testForceSaveDoesNotAdoptAnotherCustomersCart(): void
    {
        $carts = new Carts();
        $stale = $carts->getCart();
        self::assertNull($stale->id);

        $otherRequest = new Carts();
        $winner = $otherRequest->getCart();
        $customer = $this->tester->grabFixture('customers')->getElement('credentialed-user');
        $winner->setCustomer($customer);
        self::assertTrue(Craft::$app->getElements()->saveElement($winner, false));

        $cart = $carts->getCart(true);

        self::assertNotNull($cart->id);
        self::assertNotSame($winner->number, $cart->number);
        self::assertNull($cart->getCustomerId());
    }

    public function testSavingAStaleCartDirectlyDoesNotOverwriteTheWinningCart(): void
    {
        $carts = new Carts();
        $stale = $carts->getCart();
        self::assertNull($stale->id);

        $otherRequest = new Carts();
        $winner = $otherRequest->getCart(true);

        $stale->message = 'Prepared before the other request saved';
        try {
            Craft::$app->getElements()->saveElement($stale, false);
            self::fail('The stale cart should fail to save.');
        } catch (OrderNumberConflictException $e) {
            self::assertSame($this->number, $e->orderNumber);
            $stored = Order::find()->id($winner->id)->one();
            self::assertSame($winner->message, $stored->message);
        }
    }
}
