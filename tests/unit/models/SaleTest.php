<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\services\Sales;

/**
 * SaleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class SaleTest extends Unit
{
    /**
     * @todo Remove when populateSaleRelations is removed
     */
    public function testLoadRelationsCalledOnce()
    {
        $populateSaleRelationsRunCount = 0;
        $sale = new Sale();

        $mockSalesService = $this->make(Sales::class, [
            'populateSaleRelations' => function () use (&$populateSaleRelationsRunCount, &$sale) {
                $populateSaleRelationsRunCount++;
                $sale->setPurchasableIds([]);
                $sale->setCategoryIds([]);
                $sale->setUserGroupIds([]);
            }
        ]);

        Plugin::getInstance()->set('sales', $mockSalesService);
        $sale->getPurchasableIds();
        self::assertSame(0, $populateSaleRelationsRunCount, 'populateSaleRelations should no longer be called');
        $sale->getCategoryIds();
        self::assertSame(0, $populateSaleRelationsRunCount, 'populateSaleRelations should no longer be called');
    }

    /**
     *
     */
    public function testSetCategoryIds()
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
    public function testSetPurchasableIds()
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
    public function testSetUserGroupIds()
    {
       $sale = new Sale();
       $ids = [1, 2, 3, 4, 1];

        self::assertSame([], $sale->getUserGroupIds(), 'No user group IDs returns blank array');

       $sale->setUserGroupIds($ids);
        self::assertSame([1, 2, 3, 4], $sale->getUserGroupIds());
    }

    /**
     *
     */
    public function testGetApplyAmountAsPercent()
    {
        $sale = new Sale();
        $sale->applyAmount = '-0.1000';

        self::assertSame('10%', $sale->getApplyAmountAsPercent());
    }

    /**
     *
     */
    public function testGetApplyAmountAsFlat()
    {
        $sale = new Sale();
        $sale->applyAmount = '-0.1500';

        self::assertSame('0.15', $sale->getApplyAmountAsFlat());
    }
}