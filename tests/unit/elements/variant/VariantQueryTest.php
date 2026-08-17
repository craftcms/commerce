<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use Craft;
use craft\base\Element;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\purchasables\PurchasableConditionRule;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\db\Query;
use CraftCms\Commerce\CatalogPricing\Records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\elements\User;
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
                'specific-id' => [$shippingCategoryId, 1],
                'in' => [[$shippingCategoryId, 99999], 1],
                'not-in' => [['not', 99998, 99999], 3],
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
        $shippingCategoryId = $fixture->data['anotherShippingCategory']['id'];

        $matchingShippingCategory = new ShippingCategory(['id' => $shippingCategoryId]);
        $nonMatchingShippingCategory = new ShippingCategory(['id' => 99999]);

        $tests = [
            'no-params' => [null, 3],
            'specific-handle' => ['anotherShippingCategory', 1],
            'in' => [['anotherShippingCategory', 'general'], 3],
            'not-in' => [['not', 'foo', 'bar'], 3],
            'matching-shipping-category' => [$matchingShippingCategory, 1],
            'non-matching-shipping-category' => [$nonMatchingShippingCategory, 0],
        ];

        foreach ($tests as $key => [$criteria, $count]) {
            $query = Variant::find();
            $query->shippingCategory($criteria);

            self::assertCount($count, $query->all());
        }
    }

    /**
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
            'two-sites-same-store' => [['testSite1', 'defaultSite'], 6, ['testSite1' => 'primary', 'defaultSite' => 'primary']],
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
        $catalogPricingRule->apply = CatalogPricingRuleRecord::APPLY_BY_PERCENT;
        $catalogPricingRule->applyAmount = 50 / -100;
        $catalogPricingRule->applyPriceType = CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE;
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
        $catalogPricingRule->apply = CatalogPricingRuleRecord::APPLY_BY_PERCENT;
        $catalogPricingRule->applyAmount = 50 / -100;
        $catalogPricingRule->applyPriceType = CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE;
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
            'base-price-asc' => ['basePrice ASC', ['hct-white', 'hct-blue', 'rad-hood']],
            'base-price-desc' => ['basePrice DESC', array_reverse(['hct-white', 'hct-blue', 'rad-hood'])],
        ];
    }

    /**
     * @param int $expectedCount
     * @return void
     * @since 5.5.0
     * @dataProvider productStatusDataProvider
     */
    public function testProductStatus(mixed $status, int $expectedCount): void
    {
        $query = Variant::find();
        $query->productStatus($status);

        self::assertCount($expectedCount, $query->all());
    }

    /**
     * @return array[]
     */
    public function productStatusDataProvider(): array
    {
        return [
            'product-live' => ['live', 3],
            'product-live-const' => [Product::STATUS_LIVE, 3],
            'product-live-const-array' => [[Product::STATUS_LIVE], 3],
            'product-pending' => ['pending', 0],
            'product-pending-const' => [Product::STATUS_PENDING, 0],
            'product-pending-const-array' => [[Product::STATUS_PENDING], 0],
            'product-expired' => ['expired', 0],
            'product-expired-const' => [Product::STATUS_EXPIRED, 0],
            'product-expired-const-array' => [[Product::STATUS_EXPIRED], 0],
            'product-enabled' => ['enabled', 3],
            'product-enabled-const' => [Element::STATUS_ENABLED, 3],
            'product-enabled-const-array' => [[Element::STATUS_ENABLED], 3],
            'product-disabled' => ['disabled', 0],
            'product-disabled-const' => [Element::STATUS_DISABLED, 0],
            'product-disabled-const-array' => [[Element::STATUS_DISABLED], 0],
            'product-enabled-disabled' => [['enabled', 'disabled'], 3],
            'product-enabled-disabled-const' => [[Element::STATUS_ENABLED, Element::STATUS_DISABLED], 3],
            'product-not-disabled-array' => [['not', Element::STATUS_DISABLED], 3],
            'product-not-enabled' => [['not', Element::STATUS_ENABLED], 0],
        ];
    }

    /**
     * Regression test for VariantQuery::beforePrepare() checking the current
     * `commerce-viewProductType` permission (rather than the retired
     * `commerce-editProductType` permission) when the `editable` param is set.
     *
     * @return void
     * @since 5.7.0
     */
    public function testEditableRespectsViewProductTypePermission(): void
    {
        $originalIdentity = Craft::$app->getUser()->getIdentity();
        $teesUid = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts')->uid;

        $user = new User();
        $user->id = 999999;
        $user->admin = false;
        Craft::$app->getUser()->setIdentity($user);

        $this->tester->mockMethods(
            Craft::$app,
            'userPermissions',
            [
                'getPermissionsByUserId' => fn() => ["commerce-viewproducttype:$teesUid"],
            ],
            []
        );

        try {
            $query = Variant::find();
            $query->editable(true);
            $skus = $query->collect()->map(fn(Variant $v) => $v->getSku())->all();
            sort($skus);

            // Only the two variants belonging to the "tShirts" product type should be returned.
            self::assertEquals(['hct-blue', 'hct-white'], $skus);
        } finally {
            Craft::$app->getUser()->setIdentity($originalIdentity);
        }
    }

    /**
     * Regression test for VariantQuery::beforePrepare() checking the current
     * `commerce-saveProductType` permission (rather than the retired
     * `commerce-editProductType` permission) when the `savable` param is set.
     *
     * @return void
     * @since 5.7.0
     */
    public function testSavableRespectsSaveProductTypePermission(): void
    {
        $originalIdentity = Craft::$app->getUser()->getIdentity();
        $teesUid = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts')->uid;

        $user = new User();
        $user->id = 999999;
        $user->admin = false;
        Craft::$app->getUser()->setIdentity($user);

        $this->tester->mockMethods(
            Craft::$app,
            'userPermissions',
            [
                'getPermissionsByUserId' => fn() => ["commerce-saveproducttype:$teesUid"],
            ],
            []
        );

        try {
            $query = Variant::find();
            $query->savable(true);
            $skus = $query->collect()->map(fn(Variant $v) => $v->getSku())->all();
            sort($skus);

            // Only the two variants belonging to the "tShirts" product type should be returned.
            self::assertEquals(['hct-blue', 'hct-white'], $skus);
        } finally {
            Craft::$app->getUser()->setIdentity($originalIdentity);
        }
    }

    /**
     * Pins the fix: holding only the retired `commerce-editProductType` permission
     * must NOT satisfy editable()/savable() anymore.
     *
     * @return void
     * @since 5.7.0
     */
    public function testEditableIgnoresLegacyEditProductTypePermission(): void
    {
        $originalIdentity = Craft::$app->getUser()->getIdentity();
        $teesUid = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle('tShirts')->uid;

        $user = new User();
        $user->id = 999999;
        $user->admin = false;
        Craft::$app->getUser()->setIdentity($user);

        $this->tester->mockMethods(
            Craft::$app,
            'userPermissions',
            [
                'getPermissionsByUserId' => fn() => ["commerce-editproducttype:$teesUid"],
            ],
            []
        );

        try {
            $query = Variant::find();
            $query->editable(true);

            self::assertCount(0, $query->all());
        } finally {
            Craft::$app->getUser()->setIdentity($originalIdentity);
        }
    }
}
