<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Variant;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\services\Customers;
use craft\commerce\services\Sales;
use craft\db\Query;
use craft\elements\Category;
use craft\helpers\ArrayHelper;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\SalesFixture;
use Throwable;
use UnitTester;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

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
    protected UnitTester $tester;

    /**
     * @var Sales $sales
     */
    protected Sales $sales;

    /**
     * @var SalesFixture
     */
    protected SalesFixture $salesData;

    /**
     * @var string|null
     */
    private ?string $_originalEdition = null;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'customer' => [
                'class' => CustomerFixture::class,
            ],
            'sales' => [
                'class' => SalesFixture::class,
            ],
        ];
    }

    /*
     *
     */
    protected function _before(): void
    {
        parent::_before();

        $this->_originalEdition = Craft::$app->getEdition();
        Craft::$app->setEdition(Craft::Pro);
        $this->sales = Plugin::getInstance()->getSales();
        $this->salesData = $this->tester->grabFixture('sales');
    }

    /**
     *
     */
    protected function _after()
    {
        parent::_after();

        Craft::$app->setEdition($this->_originalEdition);
        $this->_originalEdition = null;
    }

    /**
     *
     */
    public function testGetAllSales(): void
    {
        $sales = $this->sales->getAllSales();
        self::assertCount(2, $sales);

        /** @var Sale $firstSale */
        $firstSale = $sales[$this->salesData->data['percentageSale']['id']] ?? null;
        self::assertNotNull($firstSale);
        self::assertSame($this->salesData->data['percentageSale']['name'], $firstSale->name);

        $variant = Variant::find()->sku('rad-hood')->one();
        self::assertSame([(int)$variant->id], array_map('intval', $firstSale->getPurchasableIds()));
        self::assertSame([], $firstSale->getUserGroupIds());
        self::assertSame([], $firstSale->getCategoryIds());
    }

    /**
     *
     */
    public function testGetSaleById(): void
    {
        $sale = $this->sales->getSaleById($this->salesData['percentageSale']['id']);
        self::assertSame($this->salesData['percentageSale']['name'], $sale->name);

        $noSale = $this->sales->getSaleById(999);
        self::assertNull($noSale);
    }

    /**
     *
     */
    public function testGetSalesForPurchasable(): void
    {
        $variant  = Variant::find()->sku('rad-hood')->one();
        $sale = $this->sales->getSaleById($this->salesData['percentageSale']['id']);

        self::assertSame([$sale], $this->sales->getSalesForPurchasable($variant));
    }

    /**
     *
     */
    public function testGetSalesRelatedToPurchasable(): void
    {
        $variant  = Variant::find()->sku('hct-white')->one();
        $sale = $this->sales->getSaleById($this->salesData['allRelationships']['id']);

        self::assertSame([$sale], $this->sales->getSalesRelatedToPurchasable($variant));
    }

    /**
     * @throws InvalidConfigException
     */
    public function testGetSalePriceForPurchasable(): void
    {
        $originalIdentity = Craft::$app->getUser()->getIdentity();
        Craft::$app->getUser()->setIdentity(
            $this->tester->grabFixture('customer')->getElement('customer1')
        );
        Craft::$app->getUser()->getIdentity()->password = '$2y$13$tAtJfYFSRrnOkIbkruGGEu7TPh0Ixvxq0r.XgWqIgNWuWpxpA7SxK';
        $variant = Variant::find()->sku('rad-hood')->one();
        $salePrice = $this->sales->getSalePriceForPurchasable($variant);

        self::assertNotSame($variant->getPrice(), $salePrice);
        self::assertEquals(111.59, $salePrice);

        $variant = Variant::find()->sku('hct-white')->one();
        $salePrice = $this->sales->getSalePriceForPurchasable($variant);

        self::assertNotSame($variant->getPrice(), $salePrice);
        self::assertEquals(15.99, $salePrice);
        Craft::$app->getUser()->setIdentity($originalIdentity);
    }

    /**
     * @throws Exception
     */
    public function testSaveSale(): void
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

        self::assertFalse($sale->hasErrors());
        self::assertTrue($saveResult);
        self::assertNotSame($originalName, $sale->name);
        self::assertSame('CHANGED', $sale->name);
        self::assertGreaterThan($originalDateUpdated, $newDateUpdated);
    }

    /**
     *
     */
    public function testReorderSales(): void
    {
        $sales = $this->sales->getAllSales();
        $originalOrder = ArrayHelper::getColumn($sales, 'id', false);
        $newOrder = array_reverse($originalOrder);

        $reorderResult = $this->sales->reorderSales($newOrder);

        self::assertTrue($reorderResult, 'Reorder sales completed');

        $dbOrder = (new Query())
            ->select(['id'])
            ->from(Table::SALES)
            ->orderBy('sortOrder asc')
            ->all();
        $dbOrder = ArrayHelper::getColumn($dbOrder, 'id', false);
        self::assertNotEquals($originalOrder, $dbOrder);
        self::assertEquals($newOrder, $dbOrder);

        // Make sure the order has updated if we retrieve the sales again in the same request
        $sales = $this->sales->getAllSales();
        $newOrderFromGetSales = ArrayHelper::getColumn($sales, 'id', false);
        self::assertEquals($newOrderFromGetSales, $dbOrder);
    }

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function testDeleteSaleById(): void
    {
        // Pre-get sales to test the memoization
        /** @noinspection PhpUnusedLocalVariableInspection */
        $originalSales = $this->sales->getAllSales();
        $id = $this->salesData['percentageSale']['id'];
        $deleteResult = $this->sales->deleteSaleById($id);

        self::assertTrue($deleteResult);
        self::assertNull($this->sales->getSaleById($id));
        self::assertFalse(array_key_exists($id, $this->sales->getAllSales()));
    }
}
