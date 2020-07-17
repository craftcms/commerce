<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\elements\Variant;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\services\Customers;
use craft\commerce\services\Sales;
use craft\db\Query;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craftcommercetests\fixtures\SalesFixture;
use UnitTester;

/**
 * SalesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
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
     * @var SalesFixture
     */
    protected $salesData;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'sales' => [
                'class' => SalesFixture::class,
            ],
        ];
    }

    protected function _before()
    {
        parent::_before();

        $this->sales = Plugin::getInstance()->getSales();
        $this->salesData = $this->tester->grabFixture('sales');
    }

    public function testGetAllSales()
    {
        $sales = $this->sales->getAllSales();
        $this->assertCount(2, $sales);

        /** @var Sale $firstSale */
        $firstSale = $sales[$this->salesData['percentageSale']['id']] ?? null;
        $this->assertNotNull($firstSale);
        $this->assertSame($this->salesData['percentageSale']['name'], $firstSale->name);

        $variant = Variant::find()->sku('rad-hood')->one();
        $this->assertSame([$variant->id], $firstSale->getPurchasableIds());
        $this->assertSame([], $firstSale->getUserGroupIds());
        $this->assertSame([], $firstSale->getCategoryIds());
    }

    public function testGetSaleById()
    {
        $sale = $this->sales->getSaleById($this->salesData['percentageSale']['id']);
        $this->assertSame($this->salesData['percentageSale']['name'], $sale->name);

        $noSale = $this->sales->getSaleById(999);
        $this->assertNull($noSale);
    }

    public function testPopulateSaleRelations()
    {
        $sale = new Sale();
        $sale->id = $this->salesData['allRelationships']['id'];

        $this->sales->populateSaleRelations($sale);

        $categoryIds = Category::find()->title(['Commerce Category', 'Commerce Category #2'])->ids();
        $purchasableIds = Variant::find()->sku('hct-white')->ids();
        $userGroupsIds = ['1002'];

        $this->assertEquals($categoryIds, $sale->getCategoryIds());
        $this->assertEquals($purchasableIds, $sale->getPurchasableIds());
        $this->assertEquals($userGroupsIds, $sale->getUserGroupIds());
    }

    public function testGetSalesForPurchasable()
    {
        $variant  = Variant::find()->sku('rad-hood')->one();
        $sale = $this->sales->getSaleById($this->salesData['percentageSale']['id']);

        $this->assertSame([$sale], $this->sales->getSalesForPurchasable($variant));
    }

    public function testGetSalesRelatedToPurchasable()
    {
        $variant  = Variant::find()->sku('hct-white')->one();
        $sale = $this->sales->getSaleById($this->salesData['allRelationships']['id']);

        $this->assertSame([$sale], $this->sales->getSalesRelatedToPurchasable($variant));
    }

    public function testGetSalePriceForPurchasable()
    {
        $variant = Variant::find()->sku('rad-hood')->one();
        $salePrice = $this->sales->getSalePriceForPurchasable($variant);

        $this->assertNotSame($variant->getPrice(), $salePrice);
        $this->assertSame(111.59, $salePrice);

        $mockCustomersService = $this->make(Customers::class, [
            'getUserGroupIdsForUser' => function () {
                return ['1002'];
            }
        ]);
        Plugin::getInstance()->set('customers', $mockCustomersService);

        $variant = Variant::find()->sku('hct-white')->one();
        $salePrice = $this->sales->getSalePriceForPurchasable($variant);

        $this->assertNotSame($variant->getPrice(), $salePrice);
        $this->assertSame(15.99, $salePrice);
    }

    public function testSaveSale()
    {
        $sale = $this->sales->getSaleById($this->salesData['allRelationships']['id']);
        $originalName = $sale->name;
        $originalDateUpdated = (new Query)
            ->select('dateUpdated')
            ->from(Table::SALES)
            ->where(['id' => $sale->id])
            ->scalar();
        $sale->name = 'CHANGED';

        // Absolutely make sure enough time has passed
        sleep(1);
        $saveResult = $this->sales->saveSale($sale);
        $newDateUpdated = (new Query())
            ->select('dateUpdated')
            ->from(Table::SALES)
            ->where(['id' => $sale->id])
            ->scalar();

        $this->assertFalse($sale->hasErrors());
        $this->assertTrue($saveResult);
        $this->assertNotSame($originalName, $sale->name);
        $this->assertSame('CHANGED', $sale->name);
        $this->assertGreaterThan($originalDateUpdated, $newDateUpdated);
    }

    public function testReorderSales()
    {
        $sales = $this->sales->getAllSales();
        $originalOrder = ArrayHelper::getColumn($sales, 'id', false);
        $newOrder = array_reverse($originalOrder);

        $reorderResult = $this->sales->reorderSales($newOrder);

        $this->assertTrue($reorderResult, 'Reorder sales completed');

        $dbOrder = (new Query())
            ->select(['id'])
            ->from(Table::SALES)
            ->orderBy('sortOrder asc')
            ->all();
        $dbOrder = ArrayHelper::getColumn($dbOrder, 'id', false);
        $this->assertNotEquals($originalOrder, $dbOrder);
        $this->assertEquals($newOrder, $dbOrder);

        // Make sure the order has updated if we retrieve the sales again in the same request
        $sales = $this->sales->getAllSales();
        $newOrderFromGetSales = ArrayHelper::getColumn($sales, 'id', false);
        $this->assertEquals($newOrderFromGetSales, $dbOrder);
    }

    public function testDeleteSaleById()
    {
        // Pre-get sales to test the memoization
        $originalSales = $this->sales->getAllSales();
        $id = $this->salesData['percentageSale']['id'];
        $deleteResult = $this->sales->deleteSaleById($id);

        $this->assertTrue($deleteResult);
        $this->assertNull($this->sales->getSaleById($id));
        $this->assertFalse(array_key_exists($id, $this->sales->getAllSales()));
    }
}
