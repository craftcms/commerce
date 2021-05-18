<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\stats\AverageOrderTotal;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * AverageOrderTotalTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class AverageOrderTotalTest extends Unit
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
     * @param float|null $average
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, $average): void
    {
        $stat = new AverageOrderTotal($dateRange, $startDate, $endDate);
        $data = $stat->get();

        if ($average === null) {
            self::assertEquals($average, $data);
        } else {
            self::assertIsNumeric($data);
        }
        self::assertEquals($average, $data);
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                AverageOrderTotal::DATE_RANGE_TODAY,
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                41.98,
            ],
            [
                AverageOrderTotal::DATE_RANGE_CUSTOM,
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                null,
            ],
        ];
    }
}
