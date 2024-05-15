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
    protected UnitTester $tester;

    /**
     * @var LineItems
     */
    protected LineItems $service;

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
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    protected function _before(): void
    {
        parent::_before();

        $this->service = Plugin::getInstance()->get('lineItems');
        $this->fixtureData = $this->tester->grabFixture('orders');
    }

    public function testGetAllLineItemsByOrderId(): void
    {
        $lineItems = $this->service->getAllLineItemsByOrderId(9999);

        self::assertIsArray($lineItems);
        self::assertCount(0, $lineItems);

        $lineItems = $this->service->getAllLineItemsByOrderId($this->fixtureData->getElement('completed-new')['id']);

        self::assertIsArray($lineItems);
        self::assertCount(2, $lineItems);
    }

    public function testResolveLineItemExisting(): void
    {
        $order = new Order();
        $variant = Variant::find()->sku('hct-blue')->one();

        $orderLineItem = $this->service->resolveLineItem($order, $variant->id, ['giftWrapped' => 'no']);

        $resolvedLineItem = $this->service->resolveLineItem($order, $variant->id, ['giftWrapped' => 'no']);

        self::assertInstanceOf(LineItem::class, $resolvedLineItem);
        // Test that resolving line items without saving is consistent
        self::assertEquals($orderLineItem->getPrice(), $resolvedLineItem->getPrice());
        self::assertEquals($orderLineItem->getSalePrice(), $resolvedLineItem->getSalePrice());
        self::assertEquals($orderLineItem->getOptionsSignature(), $resolvedLineItem->getOptionsSignature());
        self::assertEquals($orderLineItem->purchasableId, $resolvedLineItem->purchasableId);
        self::assertEquals($orderLineItem->orderId, $resolvedLineItem->orderId);
    }


    public function testResolveLineItemExistingCompletedOrder(): void
    {
        // Resolving a line item on a completed order should return a brand-new line item
        // even if the purchasable and options are the same
        /** @var Order $order */
        $order = $this->fixtureData->getElement('completed-new');
        $orderLineItem = $order->getLineItems()[0];

        $resolvedLineItem = $this->service->resolveLineItem($order, $orderLineItem->purchasableId, $orderLineItem->getOptions());

        self::assertInstanceOf(LineItem::class, $resolvedLineItem);
        // Test that resolving line items without saving is consistent
        self::assertEquals($orderLineItem->getPrice(), $resolvedLineItem->getPrice());
        self::assertEquals($orderLineItem->getSalePrice(), $resolvedLineItem->getSalePrice());
        self::assertNotEquals($orderLineItem->getOptionsSignature(), $resolvedLineItem->getOptionsSignature());
        self::assertEquals($orderLineItem->purchasableId, $resolvedLineItem->purchasableId);
        self::assertEquals($orderLineItem->orderId, $resolvedLineItem->orderId);
    }

    public function testResolveLineItemNew(): void
    {
        /** @var Order $order */
        $order = $this->fixtureData->getElement('completed-new');
        $lineItem = $order->getLineItems()[1];
        $variant = Variant::find()->id($lineItem->purchasableId)->one();

        $resolvedLineItem = $this->service->resolveLineItem($order, $lineItem->purchasableId, $lineItem->getOptions());

        self::assertInstanceOf(LineItem::class, $resolvedLineItem);
        self::assertEquals($variant->getPrice(), $resolvedLineItem->getPrice());
    }

    public function testResolveLineItemUnsavedOrder(): void
    {
        $order = new Order();
        $variant = Variant::find()->sku('hct-blue')->one();

        $resolvedLineItem = $this->service->resolveLineItem($order, $variant->id, ['giftWrapped' => 'no']);

        self::assertInstanceOf(LineItem::class, $resolvedLineItem);
        self::assertEquals($variant->getPrice(), $resolvedLineItem->getPrice());
    }

    public function testGetLineItemById(): void
    {
        $lineItems = $this->fixtureData->getElement('completed-new')->getLineItems();
        $lineItem = $this->service->getLineItemById($lineItems[0]->id);

        self::assertEquals($lineItems[0]->purchasableId, $lineItem->purchasableId);
        self::assertEquals($lineItems[0]->qty, $lineItem->qty);
    }

    public function testSnapshotUnpacking(): void
    {
        /** @var Order $order */
        $order = $this->fixtureData->getElement('completed-new');
        $lineItemById = $this->service->getLineItemById($order->getLineItems()[0]->id);
        /** @var LineItem $lineItemFromAll */
        $lineItemFromAll = collect($this->service->getAllLineItemsByOrderId($order->id))->firstWhere('id', $lineItemById->id);

        self::assertIsArray($lineItemById->getSnapshot());
        self::assertIsArray($lineItemFromAll->getSnapshot());
        self::assertEquals($lineItemById->getSnapshot(), $lineItemFromAll->getSnapshot());
    }

    public function testCreateLineItem(): void
    {
        /** @var Order $order */
        $order = $this->fixtureData->getElement('completed-new');
        $lineItem = $order->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = $this->service->createLineItem($order, $lineItem->purchasableId, $lineItem->options, $qty, $note);

        self::assertInstanceOf(LineItem::class, $lineItem);
        self::assertEquals($this->fixtureData->getElement('completed-new')->id, $lineItem->orderId);
        self::assertEquals($lineItem->purchasableId, $lineItem->purchasableId);
        self::assertEquals($lineItem->options, $lineItem->getOptions());
        self::assertEquals($qty, $lineItem->qty);
        self::assertEquals($note, $lineItem->note);
    }
}
