<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\stats\RepeatCustomers;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * RepeatCustomersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class RepeatCustomersTest extends Unit
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
     * @dataProvider getDataDataProvider
     *
     * @param string $dateRange
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param mixed $count
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, $total, $repeat, $percentage): void
    {
        $stat = new RepeatCustomers($dateRange, $startDate, $endDate);
        $data = $stat->get();

        $this->tester->assertIsArray($data);
        $this->tester->assertEquals($total, $data['total']);
        $this->tester->assertEquals($repeat, $data['repeat']);
        $this->tester->assertEquals($percentage, $data['percentage']);
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                RepeatCustomers::DATE_RANGE_TODAY,
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                1,
                100,
            ],
            [
                RepeatCustomers::DATE_RANGE_CUSTOM,
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                0,
                0,
            ],
        ];
    }
}
