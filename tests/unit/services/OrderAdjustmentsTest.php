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
use craft\commerce\records\OrderAdjustment as OrderAdjustmentRecord;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

class OrderAdjustmentsTest extends Unit
{
    protected UnitTester $tester;

    public function _fixtures(): array
    {
        return [
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    public function testEagerLoadedAdjustmentsReuseTheirOrder(): void
    {
        /** @var Order $order */
        $order = $this->tester->grabFixture('orders')->getElement('completed-new');
        $this->createAdjustment($order);

        Plugin::getInstance()->getOrderAdjustments()->eagerLoadOrderAdjustmentsForOrders([$order]);

        self::assertNotEmpty($order->getAdjustments());
        self::assertSame($order, $order->getAdjustments()[0]->getOrder());
    }

    public function testOrderAdjustmentsReuseTheirOrder(): void
    {
        /** @var Order $order */
        $order = $this->tester->grabFixture('orders')->getElement('completed-new');
        $this->createAdjustment($order);

        $order->setAdjustments([]);
        $adjustments = Plugin::getInstance()->getOrderAdjustments()->getAllOrderAdjustmentsByOrderId($order->id, $order);

        self::assertNotEmpty($adjustments);
        self::assertSame($order, $adjustments[0]->getOrder());
    }

    private function createAdjustment(Order $order): void
    {
        $record = new OrderAdjustmentRecord([
            'orderId' => $order->id,
            'type' => 'test',
            'name' => 'Test adjustment',
            'amount' => 1,
            'sourceSnapshot' => '{}',
        ]);
        self::assertTrue($record->save(false));
    }
}
