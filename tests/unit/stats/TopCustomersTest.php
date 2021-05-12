<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\models\Customer;
use craft\commerce\stats\TopCustomers;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * TopCustomersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class TopCustomersTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

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

    /**
     * @dataProvider getDataDataProvider
     *
     * @param string $dateRange
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param mixed $count
     */
    public function testGetData(string $dateRange, string $type, DateTime $startDate, DateTime $endDate, $count, $customerData): void
    {
        $stat = new TopCustomers($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        $this->tester->assertIsArray($data);
        $this->tester->assertCount($count, $data);

        if ($count !== 0) {
            $topCustomer = array_shift($data);

            $testKeys = ['total', 'average', 'customerId', 'email', 'count', 'customer'];
            foreach ($testKeys as $testKey) {
                $this->tester->assertArrayHasKey($testKey, $topCustomer);

                if ($testKey === 'customer') {
                    $this->tester->assertInstanceOf(Customer::class, $topCustomer[$testKey]);
                } else {
                    $this->tester->assertEquals($customerData[$testKey], $topCustomer[$testKey]);
                }
            }
        }
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TopCustomers::DATE_RANGE_TODAY,
                'total',
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                [
                    'total' => 83.96,
                    'average' => 41.98,
                    'customerId' => 1000,
                    'email' => 'support@craftcms.com',
                    'count' => 2,
                ]
            ],
            [
                TopCustomers::DATE_RANGE_CUSTOM,
                'total',
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                [],
            ],
        ];
    }
}
