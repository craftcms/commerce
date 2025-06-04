<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\product;

use Codeception\Test\Unit;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;

/**
 * ProductQueryTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ProductQueryTest extends Unit
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
        self::assertInstanceOf(ProductQuery::class, Product::find());
    }

    /**
     * @param int $count
     * @return void
     * @dataProvider defaultPriceDataProvider
     */
    public function testDefaultPrice(mixed $price, int $count): void
    {
        $query = Product::find();

        self::assertTrue(method_exists($query, 'defaultPrice'));
        $query->defaultPrice($price);

        self::assertCount($count, $query->all());
    }

    /**
     * @return array[]
     */
    public function defaultPriceDataProvider(): array
    {
        return [
            'exact-results' => [123.99, 1],
            'exact-no-results' => [999, 0],
            'greater-than-results' => ['> 1', 2],
            'greater-than-no-results' => ['> 999', 0],
            'less-than-results' => ['< 150', 2],
            'less-than-no-results' => ['< 1', 0],
            'range-results' => [['and', '> 5', '< 200'], 2],
            'range-no-results' => [['and', '> 500', '< 2000'], 0],
            'in-results' => [[123.99, 19.99], 2],
            'in-no-results' => [[1, 2], 0],
        ];
    }

    /**
     * @param VariantQuery $variantQuery
     * @param int $count
     * @return void
     * @dataProvider hasVariantDataProvider
     */
    public function testHasVariant(VariantQuery $variantQuery, int $count): void
    {
        $query = Product::find();

        self::assertTrue(method_exists($query, 'hasVariant'));
        $query->hasVariant($variantQuery);

        self::assertCount($count, $query->all());
    }

    /**
     * @return array[]
     */
    public function hasVariantDataProvider(): array
    {
        return [
            'no-params' => [Variant::find(), 2],
            'specific-variant' => [Variant::find()->sku('rad-hood'), 1],
        ];
    }

    /**
     * @param int $count
     * @return void
     * @dataProvider shippingCategoryIdDataProvider
     */
    public function testShippingCategoryId(mixed $shippingCategoryId, int $count): void
    {
        $query = Product::find();

        self::assertTrue(method_exists($query, 'shippingCategoryId'));
        $query->shippingCategoryId($shippingCategoryId);

        self::assertCount($count, $query->all());
    }

    /**
     * @param int $count
     * @return void
     * @dataProvider shippingCategoryIdDataProvider
     */
    public function testShippingCategoryIdProperty(mixed $shippingCategoryId, int $count): void
    {
        $query = Product::find();

        self::assertTrue(method_exists($query, 'shippingCategoryId'));
        $query->shippingCategoryId = $shippingCategoryId;

        self::assertCount($count, $query->all());
    }

    /**
     * @return array
     */
    public function shippingCategoryIdDataProvider(): array
    {
        return [
            'no-params' => [null, 2],
            'specific-id' => [101, 1],
            'in' => [[101, 102], 1],
            'not-in' => [['not', 102, 103], 2],
            'greater-than' => ['> 100', 1],
            'less-than' => ['< 100', 1],
        ];
    }

    /**
     * @param int $count
     * @return void
     * @dataProvider shippingCategoryDataProvider
     */
    public function testShippingCategory(mixed $shippingCategory, int $count): void
    {
        $query = Product::find();

        self::assertTrue(method_exists($query, 'shippingCategoryId'));
        $query->shippingCategory($shippingCategory);

        self::assertCount($count, $query->all());
    }

    /**
     * @return array
     */
    public function shippingCategoryDataProvider(): array
    {
        $matchingShippingCategory = new ShippingCategory(['id' => 101]);
        $nonMatchingShippingCategory = new ShippingCategory(['id' => 999]);

        return [
            'no-params' => [null, 2],
            'specific-handle' => ['anotherShippingCategory', 1],
            'in' => [['anotherShippingCategory', 'general'], 2],
            'not-in' => [['not', 'foo', 'bar'], 2],
            'matching-shipping-category' => [$matchingShippingCategory, 1],
            'non-matching-shipping-category' => [$nonMatchingShippingCategory, 0],
        ];
    }

    /**
     * @param int $count
     * @return void
     * @dataProvider taxCategoryIdDataProvider
     */
    public function testTaxCategoryId(mixed $taxCategoryId, int $count): void
    {
        $query = Product::find();

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
        $query = Product::find();

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
            'no-params' => [null, 2],
            'specific-id' => [101, 1],
            'in' => [[101, 102], 1],
            'not-in' => [['not', 102, 103], 2],
            'greater-than' => ['> 100', 1],
            'less-than' => ['< 100', 1],
        ];
    }

    /**
     * @param int $count
     * @return void
     * @dataProvider taxCategoryDataProvider
     */
    public function testTaxCategory(mixed $taxCategory, int $count): void
    {
        $query = Product::find();

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
            'no-params' => [null, 2],
            'specific-handle' => ['anotherTaxCategory', 1],
            'in' => [['anotherTaxCategory', 'general'], 2],
            'not-in' => [['not', 'foo', 'bar'], 2],
            'matching-tax-category' => [$matchingTaxCategory, 1],
            'non-matching-tax-category' => [$nonMatchingTaxCategory, 0],
        ];
    }
}
