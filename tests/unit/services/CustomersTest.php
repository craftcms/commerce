<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\services\Customers;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * CustomersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class CustomersTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var OrdersFixture
     */
    protected OrdersFixture $fixtureData;

    /**
     * @var array
     */
    private array $_deleteElementIds = [];

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

        $this->fixtureData = $this->tester->grabFixture('orders');
    }

    public function testOrderCompleteHandlerNotCalled(): void
    {
        Plugin::getInstance()->set('customers', $this->make(Customers::class, [
            'orderCompleteHandler' => function() {
                self::never();
            },
        ]));

        /** @var Order $completedOrder */
        $completedOrder = $this->fixtureData->getElement('completed-new');

        self::assertTrue($completedOrder->markAsComplete());
    }

    public function testOrderCompleteHandlerCalled(): void
    {
        Plugin::getInstance()->set('customers', $this->make(Customers::class, [
            'orderCompleteHandler' => function() {
                self::once();
            },
        ]));

        $order = new Order();
        $email = 'test@newemailaddress.xyz';
        $order->setEmail($email);

        /** @var Order $order */
        $completedOrder = $this->fixtureData->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order, $lineItem->purchasableId, [], $qty, $note);
        $order->setLineItems([$lineItem]);

        self::assertTrue($order->markAsComplete());

        $this->_deleteElementIds[] = $order->id;
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();

        // Cleanup data.
        foreach ($this->_deleteElementIds as $elementId) {
            \Craft::$app->getElements()->deleteElementById($elementId, null, null, true);
        }
    }
}
