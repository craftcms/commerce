<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\services\LineItems;
use craft\helpers\ArrayHelper;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * LineItemsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class LineItemsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var LineItems
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

        $this->service = Plugin::getInstance()->get('lineItems');
        $this->fixtureData = $this->tester->grabFixture('orders');
    }

    public function testGetAllLineItemsByOrderId()
    {
        $lineItems = $this->service->getAllLineItemsByOrderId(9999);

        self::assertIsArray($lineItems);
        self::assertCount(0, $lineItems);

        $lineItems = $this->service->getAllLineItemsByOrderId($this->fixtureData['completed-new']['id']);

        self::assertIsArray($lineItems);
        self::assertCount(2, $lineItems);
    }

    public function testResolveLineItemExisting()
    {
        $lineItem = $this->fixtureData['completed-new']['_lineItems'][0];
        $order = Order::find()->id($this->fixtureData['completed-new']['id'])->one();
        $orderLineItems = $order->getLineItems();

        /** @var LineItem $orderLineItem */
        $orderLineItem = ArrayHelper::firstWhere($orderLineItems, 'purchasableId', $lineItem['purchasableId']);

        $resolvedLineItem = $this->service->resolveLineItem($this->fixtureData['completed-new']['id'], $lineItem['purchasableId'], $lineItem['options']);

        self::assertInstanceOf(LineItem::class, $resolvedLineItem);
        // Test that resolving line items without saving is consistent
        self::assertEquals($orderLineItem->getPrice(), $resolvedLineItem->getPrice());
        self::assertEquals($orderLineItem->getSalePrice(), $resolvedLineItem->getSalePrice());
        self::assertEquals($orderLineItem->getOptionsSignature(), $resolvedLineItem->getOptionsSignature());
        self::assertEquals($orderLineItem->purchasableId, $resolvedLineItem->purchasableId);
        self::assertEquals($orderLineItem->orderId, $resolvedLineItem->orderId);
    }

    public function testResolveLineItemNew()
    {
        $lineItem = $this->fixtureData['completed-new']['_lineItems'][1];
        $variant = Variant::find()->id($lineItem['purchasableId'])->one();

        $resolvedLineItem = $this->service->resolveLineItem($this->fixtureData['completed-shipped']['id'], $lineItem['purchasableId'], $lineItem['options']);

        self::assertInstanceOf(LineItem::class, $resolvedLineItem);
        self::assertEquals($variant->getPrice(), $resolvedLineItem->getPrice());
    }
}
