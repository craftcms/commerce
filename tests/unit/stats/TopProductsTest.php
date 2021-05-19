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
 * @since 3.3.2
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
     * @param $productDataFunction
     */
    public function testGetData(string $dateRange,  string $type, DateTime $startDate, DateTime $endDate, int $count, $productDataFunction): void
    {
        $stat = new TopProducts($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertCount($count, $data);

        if ($count !== 0) {
            $topProduct = array_shift($data);
            $productData = $productDataFunction();

            $testKeys = ['id', 'title', 'qty', 'revenue', 'product'];
            foreach ($testKeys as $testKey) {
                self::assertArrayHasKey($testKey, $topProduct);

                if ($testKey === 'product') {
                    self::assertInstanceOf(Product::class, $topProduct[$testKey]);
                } else {
                    self::assertEquals($productData[$testKey], $topProduct[$testKey]);
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
                function() {
                    $product = Product::find()->title('Hypercolor T-shirt')->one();

                    return [
                        'id' => $product->id,
                        'title' => 'Hypercolor T-Shirt',
                        'qty' => 4,
                        'revenue' => 83.96,
                    ];
                }
            ],
            [
                TopProducts::DATE_RANGE_CUSTOM,
                'revenue',
                (new DateTime('7 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new \DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                function() {
                    return [];
                }
            ],
        ];
    }
}
