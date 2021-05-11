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
use UnitTester;

/**
 * TotalRevenueTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class TotalRevenueTest extends Unit
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
     * @param int $count
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, int $count): void
    {
        $stat = new TotalRevenue($dateRange, $startDate, $endDate);
        $data = $stat->get();

        $this->tester->assertIsArray($data);

        $todaysStats = array_pop($data);
        $this->tester->assertArrayHasKey('count', $todaysStats);
        $this->tester->assertEquals($count, $todaysStats['count']);
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TotalRevenue::DATE_RANGE_TODAY,
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                2,
            ],
        ];
    }
}
