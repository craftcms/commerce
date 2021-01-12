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
        $id = $this->fixtureData['completed-new']['id'];
        $order = $this->service->getOrderById($id);

        self::assertInstanceOf(Order::class, $order);
        self::assertEquals($id, $order->id);
    }
}
