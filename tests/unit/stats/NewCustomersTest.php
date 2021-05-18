<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\stats\NewCustomers;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * NewCustomersTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class NewCustomersTest extends Unit
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
     * @param float|null $count
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, $count): void
    {
        $stat = new NewCustomers($dateRange, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsNumeric($data);
        self::assertEquals($count, $data);
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                NewCustomers::DATE_RANGE_TODAY,
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
            ],
            [
                NewCustomers::DATE_RANGE_CUSTOM,
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
            ],
        ];
    }
}
