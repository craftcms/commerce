<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\models\Country;
use craft\commerce\stats\TotalOrdersByCountry;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use DateTimeZone;
use Exception;
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
     * @param string $type
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int $count
     * @param array $countryData
     * @throws \yii\base\Exception
     */
    public function testGetData(string $dateRange, string $type, DateTime $startDate, DateTime $endDate, int $count, array $countryData): void
    {
        $stat = new TotalOrdersByCountry($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertCount($count, $data);

        if ($count !== 0) {
            $firstItem = array_shift($data);

            foreach ($countryData as $key => $countryDatum) {
                self::assertArrayHasKey($key, $firstItem);
                self::assertEquals($countryDatum, $firstItem[$key]);
            }
        }
    }

    /**
     * @return array[]
     * @throws Exception
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TotalOrdersByCountry::DATE_RANGE_TODAY,
                'shipping',
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                [
                    'total' => 2,
                    'name' => 'United States',
                    'countryCode' => 'US',
                ],
            ],
            [
                TotalOrdersByCountry::DATE_RANGE_CUSTOM,
                'shipping',
                (new DateTime('7 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                [],
            ],
        ];
    }
}
