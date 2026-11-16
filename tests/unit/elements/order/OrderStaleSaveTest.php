<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craftcommercetests\fixtures\OrdersFixture;
use ReflectionProperty;
use UnitTester;

/**
 * Simulates the interleaving of two requests that each hold their own instance of the same order.
 * A single PHP process can't run truly concurrent requests, so each test loads two instances, lets one
 * "request" save, then saves the other (now stale) instance, which is the problematic ordering.
 *
 * @see https://github.com/craftcms/commerce/issues/4398
 */
class OrderStaleSaveTest extends Unit
{
    protected UnitTester $tester;

    private array $_deleteElementIds = [];

    public function _fixtures(): array
    {
        return [
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    protected function _after(): void
    {
        foreach ($this->_deleteElementIds as $id) {
            Craft::$app->getElements()->deleteElementById($id, Order::class, null, true);
        }
        $this->_deleteElementIds = [];

        parent::_after();
    }

    private function _createCart(): Order
    {
        $template = $this->tester->grabFixture('orders')->getElement('completed-new');
        $order = new Order();
        $order->number = Plugin::getInstance()->getCarts()->generateCartNumber();
        $order->setCustomer(Craft::$app->getUsers()->ensureUserByEmail('customer1@crafttest.com'));
        $order->setLineItems([
            Plugin::getInstance()->getLineItems()->create($order, [
                'purchasableId' => $template->getLineItems()[0]->purchasableId,
                'qty' => 1,
            ]),
        ]);
        self::assertTrue(Craft::$app->getElements()->saveElement($order, false));
        $this->_deleteElementIds[] = $order->id;

        return $order;
    }

    private function _load(int $id): Order
    {
        return Order::find()->id($id)->status(null)->withLineItems()->withAdjustments()->one();
    }

    private function _addAdjustment(Order $order, float $amount): OrderAdjustment
    {
        $adjustment = new OrderAdjustment([
            'type' => 'discount',
            'name' => 'Test ' . $amount,
            'amount' => $amount,
            'included' => false,
        ]);
        $adjustment->setOrder($order);

        return $adjustment;
    }

    private function _dbRow(int $id): array
    {
        return (new \craft\db\Query())->from(Table::ORDERS)->where(['id' => $id])->one();
    }

    public function testStaleIncompleteCopyCannotUncompleteOrder(): void
    {
        $cart = $this->_createCart();

        // Request A (e.g. a front-end page calling getCart()) loads the order while it is still a cart.
        $stale = $this->_load($cart->id);
        self::assertFalse($stale->isCompleted);

        // Request B (e.g. the payment webhook) completes the order.
        $live = $this->_load($cart->id);
        self::assertTrue($live->markAsComplete());
        $completedRow = $this->_dbRow($cart->id);
        self::assertEquals(1, $completedRow['isCompleted']);
        self::assertNotNull($completedRow['orderStatusId']);

        // Request A now saves its stale copy, as getCart() would after forcing recalculation mode `all`.
        $stale->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        Craft::$app->getElements()->saveElement($stale, false);

        $row = $this->_dbRow($cart->id);
        self::assertEquals(1, $row['isCompleted'], 'Completed order must not be un-completed by a stale save');
        self::assertEquals($completedRow['orderStatusId'], $row['orderStatusId']);
        self::assertEquals($completedRow['reference'], $row['reference']);
    }

    public function testStaleSaveReplacesAdjustmentsSoRowsMatchTotals(): void
    {
        $cart = $this->_createCart();

        // Give the order an initial adjustment, fixed at recalculation mode `none` so nothing is regenerated.
        $seed = $this->_load($cart->id);
        $seed->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        $seed->setAdjustments([$this->_addAdjustment($seed, -1.0)]);
        self::assertTrue(Craft::$app->getElements()->saveElement($seed, false));

        // Request A loads the order (holding adjustment ID X).
        $stale = $this->_load($cart->id);
        self::assertCount(1, $stale->getAdjustments());
        $staleAdjustmentId = $stale->getAdjustments()[0]->id;

        // Request B replaces the adjustments with new rows (new IDs), like a recalculation does.
        $live = $this->_load($cart->id);
        $live->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        $live->setAdjustments([$this->_addAdjustment($live, -2.0), $this->_addAdjustment($live, 7.5)]);
        self::assertTrue(Craft::$app->getElements()->saveElement($live, false));
        $liveIds = array_map(fn($a) => $a->id, $live->getAdjustments());
        self::assertNotContains($staleAdjustmentId, $liveIds);

        // Request A saves its stale copy. Its adjustment ID no longer exists, so it is saved as a new row and the
        // other request's rows are cleaned up: the rows match the totals this save wrote.
        $stale->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        self::assertTrue(Craft::$app->getElements()->saveElement($stale, false));

        $rows = (new \craft\db\Query())->select(['id', 'amount'])->from(Table::ORDERADJUSTMENTS)
            ->where(['orderId' => $cart->id])->all();
        self::assertCount(1, $rows);
        self::assertEquals(-1.0, (float)$rows[0]['amount']);
        self::assertNotContains($rows[0]['id'], $liveIds);
    }

    public function testStaleSaveWithAllAdjustmentsMissingDoesNotLeaveOrderWithoutAdjustments(): void
    {
        $cart = $this->_createCart();

        $seed = $this->_load($cart->id);
        $seed->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        $seed->setAdjustments([$this->_addAdjustment($seed, -1.0)]);
        self::assertTrue(Craft::$app->getElements()->saveElement($seed, false));

        $stale = $this->_load($cart->id);

        // Another request removes every adjustment row.
        \craft\helpers\Db::delete(Table::ORDERADJUSTMENTS, ['orderId' => $cart->id]);

        $stale->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        self::assertTrue(Craft::$app->getElements()->saveElement($stale, false));

        $count = (new \craft\db\Query())->from(Table::ORDERADJUSTMENTS)->where(['orderId' => $cart->id])->count();
        self::assertEquals(1, $count);
    }

    public function testOrderCompletedAfterBeforeSaveCheckRollsBackStaleSave(): void
    {
        $cart = $this->_createCart();

        $stale = $this->_load($cart->id);
        $stale->setRecalculationMode(Order::RECALCULATION_MODE_ALL);

        // Complete the order after the stale copy has passed the `beforeSave()` check but before its transaction opens.
        $handler = function(\craft\events\ModelEvent $event) use ($cart, &$handler) {
            \yii\base\Event::off(Order::class, Order::EVENT_BEFORE_SAVE, $handler);
            self::assertTrue($this->_load($cart->id)->markAsComplete());
        };
        \yii\base\Event::on(Order::class, Order::EVENT_BEFORE_SAVE, $handler);

        try {
            Craft::$app->getElements()->saveElement($stale, false);
            self::fail('Expected OrderCompletedException');
        } catch (\craft\commerce\errors\OrderCompletedException) {
            // expected
        } finally {
            \yii\base\Event::off(Order::class, Order::EVENT_BEFORE_SAVE, $handler);
        }

        $row = $this->_dbRow($cart->id);
        self::assertEquals(1, $row['isCompleted']);
        self::assertEquals('none', $row['recalculationMode']);
    }

    public function testGetCartDoesNotSaveCartCompletedByAnotherRequest(): void
    {
        $cart = $this->_createCart();
        $carts = Plugin::getInstance()->getCarts();

        // Request A's cart service holds the cart it loaded while incomplete.
        $stale = $this->_load($cart->id);
        $property = new ReflectionProperty($carts, '_cart');
        $property->setValue($carts, $stale);
        $carts->setSessionCartNumber($stale->number);

        // Request B completes the order.
        $live = $this->_load($cart->id);
        self::assertTrue($live->markAsComplete());
        $before = $this->_dbRow($cart->id);

        // Request A runs getCart(). Mode is not `all`, so it would normally force a recalculating save.
        $stale->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        $carts->getCart();

        $after = $this->_dbRow($cart->id);
        self::assertEquals(1, $after['isCompleted']);
        self::assertEquals('none', $after['recalculationMode'], 'getCart() must not flip a completed order back to recalculating');
        self::assertEquals($before['dateUpdated'], $after['dateUpdated']);

        $property->setValue($carts, null);
    }
}
