<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\Sale;

/**
 * SaleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class SaleTest extends Unit
{
    /**
     *
     */
    public function testSetCategoryIds(): void
    {
        $sale = new Sale();
        $ids = [1, 2, 3, 4, 1];

        self::assertSame([], $sale->getCategoryIds(), 'No category IDs returns blank array');

        $sale->setCategoryIds($ids);
        self::assertSame([1, 2, 3, 4], $sale->getCategoryIds());
    }

    /**
     *
     */
    public function testSetPurchasableIds(): void
    {
        $sale = new Sale();
        $ids = [1, 2, 3, 4, 1];

        self::assertSame([], $sale->getPurchasableIds(), 'No purchasable IDs returns blank array');

        $sale->setPurchasableIds($ids);
        self::assertSame([1, 2, 3, 4], $sale->getPurchasableIds());
    }

    /**
     *
     */
    public function testSetUserGroupIds(): void
    {
        $sale = new Sale();
        $ids = [1, 2, 3, 4, 1];

        self::assertSame([], $sale->getUserGroupIds(), 'No user group IDs returns blank array');

        $sale->setUserGroupIds($ids);
        self::assertSame([1, 2, 3, 4], $sale->getUserGroupIds());
    }

    /**
     * @dataProvider getApplyAMountAsPercentDataProvider
     */
    public function testGetApplyAmountAsPercent($applyAmount, $expected): void
    {
        $sale = new Sale();
        $sale->applyAmount = $applyAmount;

        self::assertSame($expected, $sale->getApplyAmountAsPercent());
    }

    /**
     *
     */
    public function testGetApplyAmountAsFlat(): void
    {
        $sale = new Sale();
        $sale->applyAmount = '-0.1500';

        self::assertSame('0.15', $sale->getApplyAmountAsFlat());
    }

    /**
     * @return array
     */
    public function getApplyAMountAsPercentDataProvider(): array
    {
        return [
            ['-0.1000', '10%'],
            [0, '0%'],
            [-0.1, '10%'],
            [-0.15, '15%'],
            [-0.105, '10.5%'],
            [-0.10504, '10.504%'],
            ['-0.1050400', '10.504%'],
        ];
    }
}
