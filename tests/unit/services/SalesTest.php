<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use craft\commerce\elements\Variant;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\services\Sales;
use craftcommercetests\fixtures\CategoriesFixture;
use craftcommercetests\fixtures\SaleCategoriesFixture;
use craftcommercetests\fixtures\SalePurchasablesFixture;
use craftcommercetests\fixtures\SalesFixture;
use crafttests\fixtures\UserGroupsFixture;
use UnitTester;

/**
 * SalesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class SalesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Sales $sales
     */
    protected $sales;


    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'sales' => [
                'class' => SalesFixture::class,
            ],
            'categories' => [
                'class' => CategoriesFixture::class,
            ],
            'sale-purchasables' => [
                'class' => SalePurchasablesFixture::class,
            ],
            // 'sale-categories' => [
            //     'class' => SaleCategoriesFixture::class,
            // ]
        ];
    }

    protected function _before()
    {
        parent::_before();

        $this->sales = Plugin::getInstance()->getSales();
    }

    public function testGetAllSales()
    {
        $sales = $this->sales->getAllSales();
        $this->assertCount(2, $sales);

        /** @var Sale $firstSale */
        $firstSale = $sales['1000'] ?? null;
        $this->assertNotNull($firstSale);
        $this->assertSame('My Percentage Sale', $firstSale);
        $variant = Variant::find()->sku('rad-hood')->one();
        $this->assertSame([$variant->id], $firstSale->getPurchasableIds());
        $this->assertSame([], $firstSale->getUserGroupIds());
        $this->assertSame([], $firstSale->getCategoryIds());
    }

    public function testGetSaleById()
    {
        $sale = $this->sales->getSaleById(1000);
        $this->assertSame('My Percentage Sale', $sale->name);

        $noSale = $this->sales->getSaleById(999);
        $this->assertNull($noSale);
    }

    public function testGetSalesForPurchasable()
    {
        $variant  = Variant::find()->sku('rad-hood')->one();
        $sale = $this->sales->getSaleById(1000);

        $this->assertSame([$sale], $this->sales->getSalesForPurchasable($variant));
    }
}
