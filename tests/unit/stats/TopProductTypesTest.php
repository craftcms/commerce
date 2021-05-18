<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\models\ProductType;
use craft\commerce\stats\TopProducts;
use craft\commerce\stats\TopProductTypes;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * TopProductTypesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class TopProductTypesTest extends Unit
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
     * @param array $productTypeData
     */
    public function testGetData(string $dateRange,  string $type, DateTime $startDate, DateTime $endDate, int $count, array $productTypeData): void
    {
        $stat = new TopProductTypes($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertCount($count, $data);

        if ($count !== 0) {
            $topProductType = array_shift($data);

            $testKeys = ['id', 'name', 'qty', 'revenue', 'productType'];
            foreach ($testKeys as $testKey) {
                self::assertArrayHasKey($testKey, $topProductType);

                if ($testKey === 'productType') {
                    self::assertInstanceOf(ProductType::class, $topProductType[$testKey]);
                } else {
                    self::assertEquals($productTypeData[$testKey], $topProductType[$testKey]);
                }
            }
        }
    }

    /**
     * @return array[]
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TopProducts::DATE_RANGE_TODAY,
                'revenue',
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                [
                    'id' => 2001,
                    'name' => 'T-Shirts',
                    'qty' => 4,
                    'revenue' => 83.96,
                ]
            ],
            [
                TopProducts::DATE_RANGE_CUSTOM,
                'revenue',
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                []
            ],
        ];
    }
}
