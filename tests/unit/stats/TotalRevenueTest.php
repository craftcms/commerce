<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\stats\TotalRevenue;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use DateTimeZone;
use Exception;
use UnitTester;

/**
 * TotalRevenueTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class TotalRevenueTest extends Unit
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
     * @param int $count
     * @param $revenue
     * @throws \yii\base\Exception
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, int $count, $revenue): void
    {
        $stat = new TotalRevenue($dateRange, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);

        $todaysStats = array_pop($data);
        self::assertArrayHasKey('count', $todaysStats);
        self::assertArrayHasKey('revenue', $todaysStats);
        self::assertArrayHasKey('datekey', $todaysStats);
        self::assertEquals($count, $todaysStats['count']);
        self::assertEquals($revenue, $todaysStats['revenue']);
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TotalRevenue::DATE_RANGE_TODAY,
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                2,
                83.96,
            ],
        ];
    }
}
