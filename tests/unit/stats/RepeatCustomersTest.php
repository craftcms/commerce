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
use DateTimeZone;
use Exception;
use UnitTester;

/**
 * RepeatCustomersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class RepeatCustomersTest extends Unit
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
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $total
     * @param int $repeat
     * @param int $percentage
     * @throws \yii\base\Exception
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, int $total, int $repeat, int $percentage): void
    {
        $stat = new RepeatCustomers($dateRange, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertEquals($total, $data['total']);
        self::assertEquals($repeat, $data['repeat']);
        self::assertEquals($percentage, $data['percentage']);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                RepeatCustomers::DATE_RANGE_TODAY,
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                1,
                100,
            ],
            [
                RepeatCustomers::DATE_RANGE_CUSTOM,
                (new DateTime('7 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                0,
                0,
            ],
        ];
    }
}
