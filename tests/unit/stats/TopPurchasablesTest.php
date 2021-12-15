<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\stats;

use Codeception\Test\Unit;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Variant;
use craft\commerce\stats\TopPurchasables;
use craftcommercetests\fixtures\OrdersFixture;
use DateTime;
use DateTimeZone;
use Exception;
use UnitTester;

/**
 * TopPurchasablesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.2
 */
class TopPurchasablesTest extends Unit
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
     * @param $getVariantData
     * @throws \yii\base\Exception
     */
    public function testGetData(string $dateRange,  string $type, DateTime $startDate, DateTime $endDate, int $count, $getVariantData): void
    {
        $stat = new TopPurchasables($dateRange, $type, $startDate, $endDate);
        $data = $stat->get();

        self::assertIsArray($data);
        self::assertCount($count, $data);

        if ($count !== 0) {
            $topPurchasable = array_shift($data);

            $testKeys = ['purchasableId', 'description', 'sku', 'qty', 'revenue'];
            $purchasableData = $getVariantData(Variant::find());
            foreach ($testKeys as $testKey) {
                self::assertArrayHasKey($testKey, $topPurchasable);

                self::assertEquals($purchasableData[$testKey], $topPurchasable[$testKey], 'Assert ' . $testKey);
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
            'date-today' => [
                TopPurchasables::DATE_RANGE_TODAY,
                'revenue',
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                2,
                function(VariantQuery $query) {
                    /** @var Purchasable $purchasable */
                    $variant = $query->sku('hct-blue')->one();

                    return [
                        'purchasableId' => $variant->id ?? null,
                        'description' => $variant ? $variant->getDescription() : null,
                        'sku' => 'hct-blue',
                        'qty' => 2,
                        'revenue' => 43.98,
                    ];
                }
            ],
            'date-custom' => [
                TopPurchasables::DATE_RANGE_CUSTOM,
                'qty',
                (new DateTime('7 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                (new DateTime('5 days ago', new DateTimeZone('America/Los_Angeles')))->setTime(0, 0),
                0,
                null
            ],
        ];
    }
}
