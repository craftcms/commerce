<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * OrderQueryTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.4.16
 */
class OrderQueryTest extends Unit
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
     * @param int $count
     * @return void
     * @dataProvider emailDataProvider
     */
    public function testEmail(string $email, int $count): void
    {
        $orderQuery = Order::find();
        $orderQuery->email($email);

        self::assertCount($count, $orderQuery->all());
    }

    /**
     * @return array[]
     */
    public function emailDataProvider(): array
    {
        return [
            'normal' => ['email' => 'customer1@crafttest.com', 3],
            'case-insensitive' => ['email' => 'CuStOmEr1@crafttest.com', 3],
            'no-results' => ['email' => 'null@craftcms.com', 0],
        ];
    }

    /**
     * @param int $count
     * @return void
     * @dataProvider shippingMethodHandleDataProvider
     */
    public function testShippingMethodHandle(mixed $handle, int $count): void
    {
        $orderQuery = Order::find()->isCompleted()->shippingMethodHandle($handle);
        $foo = \craft\commerce\records\Order::find()->select(['id', 'isCompleted', 'shippingMethodHandle', 'email'])->asArray()->all();
        self::assertCount($count, $orderQuery->all());
    }

    /**
     * @return array
     */
    public function shippingMethodHandleDataProvider(): array
    {
        return [
            'queryShippingByString' => ['usShipping', 1],
            'queryShippingByNotString' => ['not usShipping', 2],
            'queryShippingByArray' => [['usShipping'], 1],
            'queryShippingByNotArray' => [['not', 'usShipping'], 2],
        ];
    }
}
