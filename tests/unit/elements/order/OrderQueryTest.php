<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Exception\ModuleException;
use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\errors\TransactionException;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use craft\helpers\DateTimeHelper;
use CraftCms\Commerce\Payment\Records\Transaction as TransactionRecord;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

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
            'normal' => ['customer1@crafttest.com', 3],
            'case-insensitive' => ['CuStOmEr1@crafttest.com', 3],
            'no-results' => ['null@craftcms.com', 0],
        ];
    }

    /**
     * @param string|null $couponCode
     * @param int $count
     * @return void
     * @throws ModuleException
     * @dataProvider couponCodeDataProvider
     */
    public function testCouponCode(?string $couponCode, int $count): void
    {
        $ordersFixture = $this->tester->grabFixture('orders');
        /** @var Order $order */
        $order = $ordersFixture->getElement('completed-new');

        // Temporarily add a coupon code to an order
        \craft\commerce\records\Order::updateAll(['couponCode' => 'foo'], ['id' => $order->id]);

        $orderQuery = Order::find();
        $orderQuery->couponCode($couponCode);

        self::assertCount($count, $orderQuery->all());

        // Remove temporary coupon code
        \craft\commerce\records\Order::updateAll(['couponCode' => null], ['id' => $order->id]);
    }

    /**
     * @return array[]
     */
    public function couponCodeDataProvider(): array
    {
        return [
            'normal' => ['foo', 1],
            'case-insensitive' => ['fOo', 1],
            'using-null' => [null, 3],
            'empty-code' => [':empty:', 2],
            'not-empty-code' => [':notempty:', 1],
            'no-results' => ['nope', 0],
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

    /**
     * @param string $property
     * @param int $expected
     * @return void
     * @throws \Throwable
     * @throws CurrencyException
     * @throws OrderStatusException
     * @throws TransactionException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @since 5.5.0
     * @dataProvider paidDatesDataProvider
     */
    public function testPaidDates(string $property, mixed $value, int $expected): void
    {
        // Update one order to have paid dates for testing
        $completedOrder = Order::find()->isCompleted()->isPaid(false)->one();

        $transaction = Plugin::getInstance()->getTransactions()->createTransaction($completedOrder, typeOverride: TransactionRecord::TYPE_PURCHASE);
        $transaction->status = TransactionRecord::STATUS_SUCCESS;

        Plugin::getInstance()->getTransactions()->saveTransaction($transaction);

        $orderQuery = Order::find()
            ->{$property}($value);

        self::assertCount($expected, $orderQuery->all());

        Plugin::getInstance()->getTransactions()->deleteTransactionById($transaction->id);
    }

    public function paidDatesDataProvider(): array
    {
        $current = DateTimeHelper::currentUTCDateTime();
        $lastWeek = DateTimeHelper::lastWeek();
        $nextWeek = DateTimeHelper::nextWeek();
        return [
            'date-paid-string' => ['datePaid', '>= ' . $current->format(\DateTime::ATOM), 1],
            'date-paid-array' => ['datePaid', ['< ' . $nextWeek->format(\DateTime::ATOM), '> ' . $lastWeek->format(\DateTime::ATOM)], 1],
            'date-paid-no-results' => ['datePaid', '< ' . $lastWeek->format(\DateTime::ATOM), 0],
            'date-first-paid-string' => ['dateFirstPaid', '>= ' . $current->format(\DateTime::ATOM), 1],
            'date-first-paid-array' => ['dateFirstPaid', ['< ' . $nextWeek->format(\DateTime::ATOM), '> ' . $lastWeek->format(\DateTime::ATOM)], 1],
            'date-first-paid-no-results' => ['dateFirstPaid', '< ' . $lastWeek->format(\DateTime::ATOM), 0],
        ];
    }
}
