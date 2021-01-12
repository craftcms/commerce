<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\services\Customers;
use craft\commerce\services\Orders;
use craft\commerce\services\Sales;
use craft\db\Query;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craftcommercetests\fixtures\OrdersFixture;
use craftcommercetests\fixtures\SalesFixture;
use UnitTester;

/**
 * OrdersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class OrdersTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Orders
     */
    protected $service;

    /**
     * @var OrdersFixture
     */
    protected $fixtureData;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    /*
     *
     */
    protected function _before()
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getOrders();
        $this->fixtureData = $this->tester->grabFixture('orders');
    }

    public function testGetOrderById()
    {
        $order = $this->service->getOrderById($this->fixtureData['completed-new']['id']);

        self::assertInstanceOf(Order::class, $order);
        self::assertEquals($this->fixtureData['completed-new']['id'], $order->id);
    }

    public function testGetOrderByNumber()
    {
        $order = $this->service->getOrderByNumber($this->fixtureData['completed-new']['number']);

        self::assertInstanceOf(Order::class, $order);
        self::assertEquals($this->fixtureData['completed-new']['number'], $order->number);
        self::assertEquals($this->fixtureData['completed-new']['id'], $order->id);

        $order = $this->service->getOrderByNumber('invalid');

        self::assertNull($order);
    }

    public function testGetOrdersByCustomer()
    {
        $orders = $this->service->getOrdersByCustomer($this->fixtureData['completed-new']['customerId']);

        self::assertIsArray($orders);
        self::assertCount(2, $orders);
        foreach ($orders as $order) {
            self::assertTrue(in_array($order->id, [$this->fixtureData['completed-new']['id'], $this->fixtureData['completed-shipped']['id']]));
        }
    }

    public function testGetOrdersByEmail()
    {
        $orders = $this->service->getOrdersByEmail($this->fixtureData['completed-new']['email']);

        self::assertIsArray($orders);
        self::assertCount(2, $orders);
        foreach ($orders as $order) {
            self::assertEquals($this->fixtureData['completed-new']['email'], $order->email);
        }
    }
}
