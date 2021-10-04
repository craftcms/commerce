<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\stats\TopCustomers;
use craft\elements\User;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use DateTimeZone;
use Exception;
use UnitTester;

/**
 * TopCustomersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class TopCustomersTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

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
     * @dataProvider getDataDataProvider
     *
     * @param string $dateRange
     * @param string $type
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param mixed $count
     * @param $customerData
     * @throws \yii\base\Exception
     */
    public function testGetData(string $dateRange, string $type, DateTime $startDate, DateTime $endDate, $count, $customerData): void
    {
        $stat = new TopCustomers($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertCount($count, $data);

        if ($count !== 0) {
            $topCustomer = array_shift($data);

            $testKeys = ['total', 'average', 'customerId', 'email', 'count', 'customer'];
            foreach ($testKeys as $testKey) {
                self::assertArrayHasKey($testKey, $topCustomer);

                if ($testKey === 'customer') {
                    self::assertInstanceOf(User::class, $topCustomer[$testKey]);
                } else {
                    self::assertEquals($customerData()[$testKey], $topCustomer[$testKey]);
                }
            }
        }
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TopCustomers::DATE_RANGE_TODAY,
                'total',
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                function() {
                    $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('customer1');
                    return [
                        'total' => 83.96,
                        'average' => 41.98,
                        'customerId' => $user->id,
                        'email' => $user->email,
                        'count' => 2,
                    ];
                }
            ],
            [
                TopCustomers::DATE_RANGE_CUSTOM,
                'total',
                (new DateTime('7 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                [],
            ],
        ];
    }
}
