<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\commerce\stats\TopProducts;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use UnitTester;

/**
 * TopProductsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class TopProductsTest extends Unit
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
     * @param array $productData
     */
    public function testGetData(string $dateRange,  string $type, DateTime $startDate, DateTime $endDate, int $count, array $productData): void
    {
        $stat = new TopProducts($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        $this->tester->assertIsArray($data);
        $this->tester->assertCount($count, $data);

        if ($count !== 0) {
            $topProduct = array_shift($data);

            $testKeys = ['id', 'title', 'qty', 'revenue', 'product'];
            foreach ($testKeys as $testKey) {
                $this->tester->assertArrayHasKey($testKey, $topProduct);

                if ($testKey === 'product') {
                    $this->tester->assertInstanceOf(Product::class, $topProduct[$testKey]);
                } else {
                    $this->tester->assertEquals($productData[$testKey], $topProduct[$testKey]);
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
                'total',
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                [
                    'id' => 17,
                    'title' => 'Hypercolor T-Shirt',
                    'qty' => 4,
                    'revenue' => 83.96,
                ]
            ],
            [
                TopProducts::DATE_RANGE_CUSTOM,
                'total',
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                []
            ],
        ];
    }
}
