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
use craft\db\Query;
use craft\elements\User;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * UserEmailTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.12
 */
class UserEmailTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var string|null
     */
    private ?string $_originalEmail = null;

    /**
     * @var User|null
     */
    private ?User $_user = null;

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
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->_user = User::find()->admin()->one();
        $this->_originalEmail = $this->_user->email;
    }

    public function testUpdatedEmail(): void
    {
        $completedOrder = $this->tester->grabFixture('orders')->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $qty = 4;
        $note = 'My note';

        // Create order
        $order = new Order();
        $order->setCustomer($this->_user);
        $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order, $lineItem->purchasableId, [], $qty, $note);
        $order->setLineItems([$lineItem]);
        $order->markAsComplete();
        $this->_deleteElementIds[] = $order->id;

        // Create cart
        $cart = new Order();
        $cart->setCustomer($this->_user);
        $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($cart, $lineItem->purchasableId, [], $qty, $note);
        $cart->setLineItems([$lineItem]);
        \Craft::$app->getElements()->saveElement($cart, false, false, false);
        $this->_deleteElementIds[] = $cart->id;

        // Update email
        $newEmail = 'changed@emailaddress.xyz';
        $this->_user->email = $newEmail;
        \Craft::$app->getElements()->saveElement($this->_user, false, false ,false);

        $emails = (new Query())
            ->from(\craft\commerce\db\Table::ORDERS)
            ->select(['email'])
            ->where(['id' => [$order->id, $cart->id]])
            ->column();

        self::assertNotEmpty($emails);
        foreach ($emails as $email) {
            self::assertEquals($newEmail, $email);
        }
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();

        // Reset user email
        $this->_user->email = $this->_originalEmail;
        \Craft::$app->getElements()->saveElement($this->_user, false, false, false);
        $this->_user = null;
        $this->_originalEmail = null;

        // Cleanup data.
        foreach ($this->_deleteElementIds as $elementId) {
            \Craft::$app->getElements()->deleteElementById($elementId, null, null, true);
        }
    }
}
