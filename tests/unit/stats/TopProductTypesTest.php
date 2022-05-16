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
use craft\elements\User;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use DateTimeZone;
use Exception;
use UnitTester;

/**
 * TopProductTypesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class TopProductTypesTest extends Unit
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
     * @param array $productTypeData
     * @throws \yii\base\Exception
     */
    public function testGetData(string $dateRange,  string $type, DateTime $startDate, DateTime $endDate, int $count, array $productTypeData): void
    {
        $this->_mockUser();
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
     * @throws Exception
     */
    public function getDataDataProvider(): array
    {
        return [
            [
                TopProducts::DATE_RANGE_TODAY,
                'revenue',
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                1,
                [
                    'id' => 2001,
                    'name' => 'T-Shirts',
                    'qty' => 6,
                    'revenue' => 127.94,
                ],
            ],
            [
                TopProducts::DATE_RANGE_CUSTOM,
                'revenue',
                (new DateTime('7 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                [],
            ],
        ];
    }

    public function _mockUser(): void
    {
        $user = new User();
        $user->id = 1;
        $user->admin = true;

        $mockUser = $this->make(\craft\web\User::class, [
            'getIdentity' => $user,
        ]);

        \Craft::$app->set('user', $mockUser);
    }
}
