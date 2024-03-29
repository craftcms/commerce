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
use craft\elements\User;
use craftcommercetests\fixtures\CustomerFixture;
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
    protected UnitTester $tester;

    /**
     * @var Orders
     */
    protected Orders $service;

    /**
     * @var OrdersFixture
     */
    protected OrdersFixture $fixtureData;

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

        $this->service = Plugin::getInstance()->getOrders();
        $this->fixtureData = $this->tester->grabFixture('orders');
    }

    public function testGetOrderById(): void
    {
        $order = $this->service->getOrderById($this->fixtureData->getElement('completed-new')->id);

        self::assertInstanceOf(Order::class, $order);
        self::assertEquals($this->fixtureData->getElement('completed-new')->id, $order->id);
    }

    public function testGetOrderByNumber(): void
    {
        $order = $this->service->getOrderByNumber($this->fixtureData->getElement('completed-new')->number);

        self::assertInstanceOf(Order::class, $order);
        self::assertEquals($this->fixtureData->getElement('completed-new')->number, $order->number);
        self::assertEquals($this->fixtureData->getElement('completed-new')->id, $order->id);

        $order = $this->service->getOrderByNumber('invalid');

        self::assertNull($order);
    }

    public function testGetOrdersByCustomer(): void
    {
        /** @var User $customer */
        $customer = $this->tester->grabFixture('customer')->getElement('customer1');
        $orders = $this->service->getOrdersByCustomer($customer->id);

        self::assertIsArray($orders);
        self::assertCount(3, $orders);
        foreach ($orders as $order) {
            self::assertContains($order->id, [$this->fixtureData->getElement('completed-new')->id, $this->fixtureData->getElement('completed-new-past')->id, $this->fixtureData->getElement('completed-shipped')->id]);
        }
    }

    public function testGetOrdersByEmail(): void
    {
        /** @var Order $orderFixture */
        $orderFixture = $this->fixtureData->getElement('completed-new');
        $email = $orderFixture->getEmail();
        $orders = $this->service->getOrdersByEmail($email);

        self::assertIsArray($orders);
        self::assertCount(3, $orders);
        foreach ($orders as $order) {
            self::assertEquals($email, $order->getEmail());
        }
    }
}
