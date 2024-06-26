<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\Plugin;
use craft\commerce\stats\TotalOrders;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use DateTimeZone;
use Exception;
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
     * @param int $daysDiff
     * @throws \yii\base\Exception
     */
    public function testGetData(string $dateRange, DateTime $startDate, DateTime $endDate, int $total, int $daysDiff): void
    {
        $storeId = Plugin::getInstance()->getStores()->getPrimaryStore()->id;
        $stat = new TotalOrders($dateRange, $startDate, $endDate, $storeId);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertArrayHasKey('total', $data);
        self::assertEquals($total, $data['total']);
        self::assertArrayHasKey('chart', $data);
        self::assertIsArray($data['chart']);
        self::assertArrayHasKey($startDate->format('Y-m-d'), $data['chart']);
        self::assertArrayHasKey($endDate->format('Y-m-d'), $data['chart']);
        self::assertCount($daysDiff, $data['chart']);

        $firstItem = array_shift($data['chart']);
        self::assertArrayHasKey('total', $firstItem);
        self::assertArrayHasKey('datekey', $firstItem);
        self::assertEquals($startDate->format('Y-m-d'), $firstItem['datekey']);
        self::assertEquals($total, $firstItem['total']);
    }

    protected function _before(): void
    {
        Craft::$app->setTimeZone('America/Los_Angeles');
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function getDataDataProvider(): array
    {
        return [
            'today' => [
                TotalOrders::DATE_RANGE_TODAY,
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                2,
                1,
            ],
            'custom' => [
                TotalOrders::DATE_RANGE_CUSTOM,
                (new DateTime('7 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                3,
            ],
        ];
    }
}
