<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\Plugin;
use craft\commerce\services\Customers;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;
use yii\base\Exception;
use yii\base\InvalidConfigException;

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
        $user = \Craft::$app->getUsers()->ensureUserByEmail('test@newemailaddress.xyz');
        $order->setCustomer($user);

        /** @var Order $order */
        $completedOrder = $this->fixtureData->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order, $lineItem->purchasableId, [], $qty, $note);
        $order->setLineItems([$lineItem]);

        self::assertTrue($order->markAsComplete());

        $this->_deleteElementIds[] = $order->id;
        $this->_deleteElementIds[] = $user->id;
    }

    /**
     * @param string $email
     * @param bool $register
     * @param bool $deleteUser an argument to help with cleanup
     * @return void
     * @throws \Throwable
     * @throws OrderStatusException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @dataProvider registerOnCheckoutDataProvider
     */
    public function testRegisterOnCheckout(string $email, bool $register, bool $deleteUser): void
    {
        $order = new Order();
        $user = \Craft::$app->getUsers()->ensureUserByEmail($email);
        $originallyCredentialed = $user->getIsCredentialed();
        $order->setCustomer($user);

        $order->registerUserOnOrderComplete = $register;

        $completedOrder = $this->fixtureData->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order, $lineItem->purchasableId, [], $qty, $note);
        $order->setLineItems([$lineItem]);

        self::assertTrue($order->markAsComplete());

        $foundUser = User::find()->email($email)->status(null)->one();
        self::assertNotNull($foundUser);

        if ($register || $originallyCredentialed) {
            self::assertTrue($foundUser->getIsCredentialed());
        } else {
            self::assertFalse($foundUser->getIsCredentialed());
        }

        $this->_deleteElementIds[] = $order->id;
        if ($deleteUser) {
            $this->_deleteElementIds[] = $user->id;
        }
    }

    /**
     * @return array[]
     */
    public function registerOnCheckoutDataProvider(): array
    {
        return [
            'dont-register-guest' => ['guest@customer.xyz', false, true],
            'register-guest' => ['guest@customer.xyz', true, true],
            'register-credentialed-user' => ['cred.user@crafttest.com', true, false],
            'dont-register-credentialed-user' => ['cred.user@crafttest.com', false, false],
        ];
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
