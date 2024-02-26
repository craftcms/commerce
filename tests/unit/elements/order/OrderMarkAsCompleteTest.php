<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * OrderMarkAsCompleteTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.12
 */
class OrderMarkAsCompleteTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Plugin|null
     */
    protected ?Plugin $pluginInstance;

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
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    /**
     *
     */
    public function testUpdatedProperties(): void
    {
        $order = new Order();
        $email = 'test@newemailaddress.xyz';
        $user = \Craft::$app->getUsers()->ensureUserByEmail($email);
        $order->setCustomer($user);
        /** @var Order $order */
        $completedOrder = $this->tester->grabFixture('orders')->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = $this->pluginInstance->getLineItems()->createLineItem($order, $lineItem->purchasableId, [], $qty, $note);
        $order->setLineItems([$lineItem]);

        self::assertNull($order->dateOrdered);
        self::assertFalse($order->isCompleted);
        self::assertNull($order->orderCompletedEmail);

        self::assertTrue($order->markAsComplete());

        self::assertInstanceOf(\DateTime::class, $order->dateOrdered);
        self::assertTrue($order->isCompleted);
        self::assertEquals($email, $order->orderCompletedEmail);

        $this->_deleteElementIds[] = $order->id;
        $this->_deleteElementIds[] = $user->id;
    }

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
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
