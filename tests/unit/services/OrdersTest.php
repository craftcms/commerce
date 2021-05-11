<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\services\Orders;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * OrdersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
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

    protected function _before()
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getOrders();
        $this->fixtureData = $this->tester->grabFixture('orders');
    }

    public function testGetOrderById()
    {
        $order = $this->service->getOrderById($this->fixtureData->getElement('completed-new')->id);

        self::assertInstanceOf(Order::class, $order);
        self::assertEquals($this->fixtureData->getElement('completed-new')->id, $order->id);
    }

    public function testGetOrderByNumber()
    {
        $order = $this->service->getOrderByNumber($this->fixtureData->getELement('completed-new')->number);

        self::assertInstanceOf(Order::class, $order);
        self::assertEquals($this->fixtureData->getElement('completed-new')->number, $order->number);
        self::assertEquals($this->fixtureData->getElement('completed-new')->id, $order->id);

        $order = $this->service->getOrderByNumber('invalid');

        self::assertNull($order);
    }

    public function testGetOrdersByCustomer()
    {
        $orders = $this->service->getOrdersByCustomer($this->fixtureData->getELement('completed-new')->customerId);

        self::assertIsArray($orders);
        self::assertCount(3, $orders);
        foreach ($orders as $order) {
            self::assertContains($order->id, [$this->fixtureData->getElement('completed-new')->id, $this->fixtureData->getElement('completed-new-past')->id, $this->fixtureData->getElement('completed-shipped')->id]);
        }
    }

    public function testGetOrdersByEmail()
    {
        $orders = $this->service->getOrdersByEmail($this->fixtureData->getElement('completed-new')->email);

        self::assertIsArray($orders);
        self::assertCount(3, $orders);
        foreach ($orders as $order) {
            self::assertEquals($this->fixtureData->getElement('completed-new')->email, $order->email);
        }
    }
}
