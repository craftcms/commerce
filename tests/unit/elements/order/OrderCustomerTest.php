<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\services\Deprecator;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * OrderCustomerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class OrderCustomerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

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
     * @param string $email
     * @return void
     * @throws Exception
     * @throws InvalidConfigException
     * @dataProvider emailDataProvider
     */
    public function testSetEmail(string $email): void
    {
        \Craft::$app->set('deprecator', $this->make(Deprecator::class, [
            'log' => function(string $key, string $message, ?string $file = null, ?int $line = null) {
                self::once();
                self::assertEquals(Order::class . '::setEmail', $key);
            },
        ]));

        $order = new Order();
        $order->setEmail($email);

        self::assertEquals($email, $order->getEmail());
        self::assertNotNull($order->getCustomer());
        self::assertEquals($email, $order->getCustomer()->email);
    }

    /**
     * @return array[]
     */
    public function emailDataProvider(): array
    {
        return [
            'existing-credentialed-user' => ['email' => 'customer1@crafttest.com'],
            'existing-inactive-user' => ['email' => 'inactive.user@crafttest.com'],
        ];
    }

    /**
     * @param string $email
     * @return void
     * @dataProvider customerDataProvider
     */
    public function testSetCustomer(string $email): void
    {
        $user = \Craft::$app->getUsers()->getUserByUsernameOrEmail($email);
        $order = new Order();
        $order->setCustomer($user);

        self::assertEquals($email, $order->getEmail());
        self::assertNotNull($order->getCustomer());
        self::assertEquals($email, $order->getCustomer()->email);
        self::assertEquals($user->id, $order->getCustomer()->id);
        self::assertEquals($user->id, $order->getCustomerId());

        // Test remove customer
        $order->setCustomer();
        self::assertNull($order->getCustomer());
        self::assertNull($order->getCustomerId());
        self::assertNull($order->getEmail());
    }

    /**
     * @return array[]
     */
    public function customerDataProvider(): array
    {
        return [
            'existing-credentialed-user' => ['email' => 'customer1@crafttest.com'],
            'existing-inactive-user' => ['email' => 'inactive.user@crafttest.com'],
        ];
    }
}
