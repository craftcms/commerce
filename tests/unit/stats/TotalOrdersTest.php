<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\stats\TotalOrders;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * TotalOrdersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class TotalOrdersTest extends Unit
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
     * @param int $total
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, int $total): void
    {
        $stat = new TotalOrders($dateRange, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertArrayHasKey('total', $data);
        self::assertEquals($total, $data['total']);
        self::assertArrayHasKey('chart', $data);
        self::assertIsArray($data['chart']);
        self::assertArrayHasKey($startDate->format('Y-m-d'), $data['chart']);
        self::assertArrayHasKey($endDate->format('Y-m-d'), $data['chart']);
        self::assertCount($endDate->diff($startDate)->days + 1, $data['chart']);

        $firstItem = array_shift($data['chart']);
        self::assertArrayHasKey('total', $firstItem);
        self::assertArrayHasKey('datekey', $firstItem);
        self::assertEquals($startDate->format('Y-m-d'), $firstItem['datekey']);
        self::assertEquals($total, $firstItem['total']);
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TotalOrders::DATE_RANGE_TODAY,
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                2,
            ],
            [
                TotalOrders::DATE_RANGE_CUSTOM,
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
            ],
        ];
    }
}
