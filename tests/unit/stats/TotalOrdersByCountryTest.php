<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\models\Country;
use craft\commerce\stats\TotalOrders;
use craft\commerce\stats\TotalOrdersByCountry;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * TotalOrdersByCountryTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class TotalOrdersByCountryTest extends Unit
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
     * @param string $type
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $count
     */
    public function testGetData(string $dateRange, string $type, DateTime $startDate, DateTime $endDate, int $count, array $countryData): void
    {
        $stat = new TotalOrdersByCountry($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertCount($count, $data);

        if ($count !== 0) {
            $firstItem = array_shift($data);
            self::assertArrayHasKey('total', $firstItem);
            self::assertEquals($countryData['total'], $firstItem['total']);
            self::assertArrayHasKey('id', $firstItem);
            self::assertEquals($countryData['id'], $firstItem['id']);
            self::assertArrayHasKey('name', $firstItem);
            self::assertEquals($countryData['name'], $firstItem['name']);
            self::assertArrayHasKey('country', $firstItem);
            self::assertInstanceOf(Country::class, $firstItem['country']);
        }
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TotalOrdersByCountry::DATE_RANGE_TODAY,
                'shipping',
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                [
                    'total' => 2,
                    'id' => 236,
                    'name' => 'United States',
                ],
            ],
            [
                TotalOrdersByCountry::DATE_RANGE_CUSTOM,
                'shipping',
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                [],
            ],
        ];
    }
}
