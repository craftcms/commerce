<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\services\LineItems;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * LineItemsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
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

        $lineItems = $this->service->getAllLineItemsByOrderId($this->fixtureData->getElement('completed-new')['id']);

        self::assertIsArray($lineItems);
        self::assertCount(2, $lineItems);
    }

    public function testResolveLineItemExisting()
    {
        $order = $this->fixtureData->getElement('completed-new');
        /** @var LineItem $orderLineItem */
        $orderLineItem = $order->getLineItems()[0];

        $resolvedLineItem = $this->service->resolveLineItem($order->id, $orderLineItem->purchasableId, $orderLineItem->getOptions());

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
        $lineItem = $this->fixtureData->getElement('completed-new')->getLineItems()[1];
        $variant = Variant::find()->id($lineItem->purchasableId)->one();

        $resolvedLineItem = $this->service->resolveLineItem($this->fixtureData->getElement('completed-shipped')->id, $lineItem->purchasableId, $lineItem->getOptions());

        self::assertInstanceOf(LineItem::class, $resolvedLineItem);
        self::assertEquals($variant->getPrice(), $resolvedLineItem->getPrice());
    }

    public function testGetLineItemById()
    {
        $lineItems = $this->fixtureData->getElement('completed-new')->getLineItems();
        $lineItem = $this->service->getLineItemById($lineItems[0]->id);

        self::assertEquals($lineItems[0]->purchasableId, $lineItem->purchasableId);
        self::assertEquals($lineItems[0]->qty, $lineItem->qty);
    }

    public function testCreateLineItem()
    {
        $lineItem = $this->fixtureData->getElement('completed-new')->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = $this->service->createLineItem($this->fixtureData->getElement('completed-new')->id, $lineItem->purchasableId, $lineItem->options, $qty, $note);

        self::assertInstanceOf(LineItem::class, $lineItem);
        self::assertEquals($this->fixtureData->getElement('completed-new')->id, $lineItem->orderId);
        self::assertEquals($lineItem->purchasableId, $lineItem->purchasableId);
        self::assertEquals($lineItem->options, $lineItem->getOptions());
        self::assertEquals($qty, $lineItem->qty);
        self::assertEquals($note, $lineItem->note);
    }
}
