<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\purchasables\PurchasableConditionRule;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\db\Query;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\ShippingCategoryFixture;
use UnitTester;

/**
 * VariantQueryTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class VariantQueryTest extends Unit
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
            'shippingCategories' => [
                'class' => ShippingCategoryFixture::class,
            ],
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    /**
     * @return void
     */
    public function testQuery(): void
    {
        self::assertInstanceOf(VariantQuery::class, Variant::find());
    }

    /**
     * @return void
     */
    public function testShippingCategoryId(): void
    {
        self::assertTrue(method_exists(Variant::find(), 'shippingCategoryId'), 'shippingCategoryId method exists');

        [$shippingCategoryId, $tests] = $this->_getShippingCategoryIdData();

        foreach ($tests as $key => [$criteria, $count]) {
            $query = Variant::find();
            $query->shippingCategoryId($criteria);

            self::assertCount($count, $query->all(), "shippingCategoryId Test $key");
        }
    }

    /**
     * @return void
     */
    public function testShippingCategoryIdProperty(): void
    {
        self::assertTrue(property_exists(Variant::find(), 'shippingCategoryId'), 'shippingCategoryId property exists');

        [$shippingCategoryId, $tests] = $this->_getShippingCategoryIdData();

        foreach ($tests as $key => [$criteria, $count]) {
            $query = Variant::find();
            $query->shippingCategoryId = $criteria;
            self::assertCount($count, $query->all(), "shippingCategoryIdProperty Test $key");
        }
    }

    /**
     * @return array
     */
    private function _getShippingCategoryIdData(): array
    {
        $fixture = $this->tester->grabFixture('shippingCategories');
        $shippingCategoryId = $fixture->data['anotherShippingCategory']['id'];

        return [
            $shippingCategoryId,
            [
                'no-params' => [null, 3],
                'specific-id' => [$shippingCategoryId, 3],
                'in' => [[$shippingCategoryId, 99999], 3],
                'not-in' => [['not', 99998, 99999], 3],
                'greater-than' => ['> ' . ($shippingCategoryId - 1), 3],
                'less-than' => ['< ' . ($shippingCategoryId), 0],
            ],
        ];
    }

    /**
     * @return void
     */
    public function testShippingCategory(): void
    {
        self::assertTrue(method_exists(Variant::find(), 'shippingCategoryId'));
        $fixture = $this->tester->grabFixture('shippingCategories');
        $matchingShippingCategory = new ShippingCategory(['id' => $fixture->data['anotherShippingCategory']['id']]);
        $nonMatchingShippingCategory = new ShippingCategory(['id' => 99999]);

        $tests = [
            'no-params' => [null, 3],
            'specific-handle' => ['anotherShippingCategory', 3],
            'in' => [['anotherShippingCategory', 'general'], 3],
            'not-in' => [['not', 'foo', 'bar'], 3],
            'matching-shipping-category' => [$matchingShippingCategory, 3],
            'non-matching-shipping-category' => [$nonMatchingShippingCategory, 0],
        ];

        foreach ($tests as $key => [$criteria, $count]) {
            $query = Variant::find();
            $query->shippingCategory($criteria);

            self::assertCount($count, $query->all());
        }
    }

    /**
     * @param mixed $taxCategoryId
     * @param int $count
     * @return void
     * @dataProvider taxCategoryIdDataProvider
     */
    public function testTaxCategoryId(mixed $taxCategoryId, int $count): void
    {
        $query = Variant::find();

        self::assertTrue(method_exists($query, 'taxCategoryId'));
        $query->taxCategoryId($taxCategoryId);

        self::assertCount($count, $query->all());
    }

    /**
     * @param mixed $taxCategoryId
     * @param int $count
     * @return void
     * @dataProvider taxCategoryIdDataProvider
     */
    public function testTaxCategoryIdProperty(mixed $taxCategoryId, int $count): void
    {
        $query = Variant::find();

        self::assertTrue(method_exists($query, 'taxCategoryId'));
        $query->taxCategoryId = $taxCategoryId;

        self::assertCount($count, $query->all());
    }

    /**
     * @return array
     */
    public function taxCategoryIdDataProvider(): array
    {
        return [
            'no-params' => [null, 3],
            'specific-id' => [101, 3],
            'in' => [[101, 102], 3],
            'not-in' => [['not', 102, 103], 3],
            'greater-than' => ['> 100', 3],
            'less-than' => ['< 100', 0],
        ];
    }

    /**
     * @param mixed $taxCategory
     * @param int $count
     * @return void
     * @dataProvider taxCategoryDataProvider
     */
    public function testTaxCategory(mixed $taxCategory, int $count): void
    {
        $query = Variant::find();

        self::assertTrue(method_exists($query, 'taxCategoryId'));
        $query->taxCategory($taxCategory);

        self::assertCount($count, $query->all());
    }

    /**
     * @return array
     */
    public function taxCategoryDataProvider(): array
    {
        $matchingTaxCategory = new TaxCategory(['id' => 101]);
        $nonMatchingTaxCategory = new TaxCategory(['id' => 999]);

        return [
            'no-params' => [null, 3],
            'specific-handle' => ['anotherTaxCategory', 3],
            'in' => [['anotherTaxCategory', 'general'], 3],
            'not-in' => [['not', 'foo', 'bar'], 3],
            'matching-tax-category' => [$matchingTaxCategory, 3],
            'non-matching-tax-category' => [$nonMatchingTaxCategory, 0],
        ];
    }

    /**
     * @param array $sites
     * @return void
     * @dataProvider queryingBySiteDataProvider
     */
    public function testQueryingBySite(array $sites, int $count, array $siteHandleToStoreHandle): void
    {
        $query = Variant::find();
        $query->site($sites);
        $results = $query->all();

        // Assert the correct number of results
        self::assertCount($count, $results);

        // Check that by querying site the correct store is returned
        foreach ($results as $variant) {
            self::assertSame($siteHandleToStoreHandle[$variant->getSite()->handle], $variant->getStore()->handle);
        }
    }

    public function queryingBySiteDataProvider(): array
    {
        return [
            'one-site' => [['testSite1'], 3, ['testSite1' => 'primary']],
            'two-sites-same-store' => [['testSite1', 'default'], 6, ['testSite1' => 'primary', 'default' => 'primary']],
            'two-sites-different-stores' => [['testSite1', 'testSite2'], 6, ['testSite1' => 'primary', 'testSite2' => 'euStore']],
        ];
    }

    /**
     * @return void
     */
    public function testHasPricePropertiesPopulated(): void
    {
        $query = Variant::find();
        $results = $query->all();

        foreach ($results as $variant) {
            self::assertNotNull($variant->price);
            self::assertNotNull($variant->salePrice);
        }
    }

    public function testPriceQueryForCatalogPricingRule(): void
    {
        // Create on the fly catalog pricing rule
        $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
        $catalogPricingRule = new CatalogPricingRule();
        $catalogPricingRule->apply = \craft\commerce\records\CatalogPricingRule::APPLY_BY_PERCENT;
        $catalogPricingRule->applyAmount = 50 / -100;
        $catalogPricingRule->applyPriceType = \craft\commerce\records\CatalogPricingRule::APPLY_PRICE_TYPE_PRICE;
        $catalogPricingRule->dateFrom = null;
        $catalogPricingRule->dateTo = null;
        $catalogPricingRule->description = '';
        $catalogPricingRule->enabled = true;
        $catalogPricingRule->isPromotionalPrice = false;
        $catalogPricingRule->name = 'Test';
        $catalogPricingRule->storeId = $primaryStore->id;

        $purchasableCondition = $catalogPricingRule->getPurchasableCondition();
        $purchasableConditionRule = new PurchasableConditionRule();
        $purchasableConditionRule->setElementIds([
            'craft\\commerce\\elements\\Variant' => [(new Query())->from(Table::PURCHASABLES)->select('id')->where(['sku' => 'hct-blue'])->scalar()],
        ]);

        $purchasableCondition->addConditionRule($purchasableConditionRule);

        $catalogPricingRule->setPurchasableCondition($purchasableCondition);
        Plugin::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($catalogPricingRule);

        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();

        $query = Variant::find();
        $query->price('<= 11');
        $results = $query->all();

        self::assertCount(1, $results);
        self::assertSame('hct-blue', $results[0]->sku);
        self::assertEquals(11, $results[0]->getPrice());

        // Check sale price
        $query = Variant::find();
        $query->salePrice('<= 11');
        $results = $query->all();

        self::assertCount(1, $results);
        self::assertSame('hct-blue', $results[0]->sku);
        self::assertEquals(11, $results[0]->getSalePrice());

        // Delete the catalog pricing rule
        Plugin::getInstance()->getCatalogPricingRules()->deleteCatalogPricingRuleById($catalogPricingRule->id);
        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
    }

    public function testPromotionalPriceQueryForCatalogPricingRule(): void
    {
        // Create on the fly catalog pricing rule
        $primaryStore = Plugin::getInstance()->getStores()->getPrimaryStore();
        $catalogPricingRule = new CatalogPricingRule();
        $catalogPricingRule->apply = \craft\commerce\records\CatalogPricingRule::APPLY_BY_PERCENT;
        $catalogPricingRule->applyAmount = 50 / -100;
        $catalogPricingRule->applyPriceType = \craft\commerce\records\CatalogPricingRule::APPLY_PRICE_TYPE_PRICE;
        $catalogPricingRule->dateFrom = null;
        $catalogPricingRule->dateTo = null;
        $catalogPricingRule->description = '';
        $catalogPricingRule->enabled = true;
        $catalogPricingRule->isPromotionalPrice = true;
        $catalogPricingRule->name = 'Test';
        $catalogPricingRule->storeId = $primaryStore->id;

        $purchasableCondition = $catalogPricingRule->getPurchasableCondition();
        $purchasableConditionRule = new PurchasableConditionRule();
        $purchasableConditionRule->setElementIds([
            'craft\\commerce\\elements\\Variant' => [(new Query())->from(Table::PURCHASABLES)->select('id')->where(['sku' => 'hct-blue'])->scalar()],
        ]);

        $purchasableCondition->addConditionRule($purchasableConditionRule);

        $catalogPricingRule->setPurchasableCondition($purchasableCondition);
        Plugin::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($catalogPricingRule);

        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();

        $query = Variant::find();
        $query->promotionalPrice('<= 11');
        $results = $query->all();

        self::assertCount(1, $results);
        self::assertSame('hct-blue', $results[0]->sku);
        self::assertEquals(11, $results[0]->getPromotionalPrice());

        // Check sale price
        $query = Variant::find();
        $query->salePrice('<= 11');
        $results = $query->all();

        self::assertCount(1, $results);
        self::assertSame('hct-blue', $results[0]->sku);
        self::assertEquals(11, $results[0]->getSalePrice());

        // Check the price hasn't been altered
        $query = Variant::find();
        $query->sku('hct-blue');
        $query->price('> 11');
        $results = $query->all();

        self::assertCount(1, $results);
        self::assertSame('hct-blue', $results[0]->sku);
        self::assertEquals(21.99, $results[0]->getPrice());

        // Delete the catalog pricing rule
        Plugin::getInstance()->getCatalogPricingRules()->deleteCatalogPricingRuleById($catalogPricingRule->id);
        Plugin::getInstance()->getCatalogPricing()->generateCatalogPrices();
    }

    /**
     * @param mixed $orderBy
     * @param array $expectedSkuOrder
     * @return void
     * @dataProvider orderByDataProvider
     */
    public function testOrderBy(mixed $orderBy, array $expectedSkuOrder): void
    {
        $query = Variant::find();
        $query->orderBy($orderBy);

        $results = $query->collect()->map(fn(Variant $v) => $v->getSku())->all();

        self::assertEquals($expectedSkuOrder, $results);
    }

    /**
     * @return array[]
     */
    public function orderByDataProvider(): array
    {
        return [
            'sku-asc' => ['sku ASC', ['hct-blue', 'hct-white', 'rad-hood']],
            'price-asc' => ['price ASC', ['hct-white', 'hct-blue', 'rad-hood']],
            'price-desc' => ['price DESC', array_reverse(['hct-white', 'hct-blue', 'rad-hood'])],
            'sale-price-asc' => ['salePrice ASC', ['hct-white', 'hct-blue', 'rad-hood']],
            'sale-price-desc' => ['salePrice DESC', array_reverse(['hct-white', 'hct-blue', 'rad-hood'])],
        ];
    }
}
