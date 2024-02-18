<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Variant;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\TaxCategory;
use craftcommercetests\fixtures\ProductFixture;
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
     * @param mixed $shippingCategoryId
     * @param int $count
     * @return void
     * @dataProvider shippingCategoryIdDataProvider
     */
    public function testShippingCategoryId(mixed $shippingCategoryId, int $count): void
    {
        $query = Variant::find();

        self::assertTrue(method_exists($query, 'shippingCategoryId'));
        $query->shippingCategoryId($shippingCategoryId);

        self::assertCount($count, $query->all());
    }

    /**
     * @param mixed $shippingCategoryId
     * @param int $count
     * @return void
     * @dataProvider shippingCategoryIdDataProvider
     */
    public function testShippingCategoryIdProperty(mixed $shippingCategoryId, int $count): void
    {
        $query = Variant::find();

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
            'no-params' => [null, 3],
            'specific-id' => [101, 3],
            'in' => [[101, 102], 3],
            'not-in' => [['not', 102, 103], 3],
            'greater-than' => ['> 100', 3],
            'less-than' => ['< 100', 0],
        ];
    }

    /**
     * @param mixed $shippingCategory
     * @param int $count
     * @return void
     * @dataProvider shippingCategoryDataProvider
     */
    public function testShippingCategory(mixed $shippingCategory, int $count): void
    {
        $query = Variant::find();

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
            'no-params' => [null, 3],
            'specific-handle' => ['anotherShippingCategory', 3],
            'in' => [['anotherShippingCategory', 'general'], 3],
            'not-in' => [['not', 'foo', 'bar'], 3],
            'matching-shipping-category' => [$matchingShippingCategory, 3],
            'non-matching-shipping-category' => [$nonMatchingShippingCategory, 0],
        ];
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
}
