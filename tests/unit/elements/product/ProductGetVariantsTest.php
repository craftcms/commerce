<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\product;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\db\VariantQuery;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\elements\VariantCollection;
use craft\controllers\NestedElementsController;
use craftcommercetests\fixtures\ProductFixture;
use ReflectionClass;

/**
 * ProductGetVariantsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.4.4
 */
class ProductGetVariantsTest extends Unit
{
    /**
     * @var \UnitTester
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
     * Test that getVariants() returns empty collection when product has no ID
     */
    public function testGetVariantsReturnsEmptyCollectionForNewProduct(): void
    {
        $product = new Product();
        $variants = $product->getVariants();

        self::assertInstanceOf(VariantCollection::class, $variants);
        self::assertTrue($variants->isEmpty());
    }

    /**
     * Test that getVariants() doesn't memoize empty collections
     */
    public function testGetVariantsDoesNotMemoizeEmptyCollections(): void
    {
        $product = new Product();
        $product->id = 999999; // Non-existent ID
        $product->typeId = 2000;

        // First call should return empty collection
        $variants1 = $product->getVariants();
        self::assertTrue($variants1->isEmpty());

        // Access private _variants property to check if it was memoized
        $reflection = new ReflectionClass($product);
        $variantsProperty = $reflection->getProperty('_variants');
        $variantsProperty->setAccessible(true);

        // Should be null, not an empty collection
        self::assertNull($variantsProperty->getValue($product));

        // Second call should also return empty collection (new query)
        $variants2 = $product->getVariants();
        self::assertTrue($variants2->isEmpty());

        // Still should not be memoized
        self::assertNull($variantsProperty->getValue($product));
    }

    /**
     * Test that getVariants() memoizes non-empty collections
     */
    public function testGetVariantsMemoizesNonEmptyCollections(): void
    {
        // Get a product that has variants from fixtures
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $product = $productFixture->getElement('rad-hoodie');
        self::assertNotNull($product);

        // First call
        $variants1 = $product->getVariants();
        self::assertFalse($variants1->isEmpty());

        // Access private _variants property
        $reflection = new ReflectionClass($product);
        $variantsProperty = $reflection->getProperty('_variants');
        $variantsProperty->setAccessible(true);

        // Should be memoized
        $memoizedVariants = $variantsProperty->getValue($product);
        self::assertInstanceOf(VariantCollection::class, $memoizedVariants);
        self::assertNotNull($memoizedVariants);

        // Second call should return the memoized collection
        $variants2 = $product->getVariants();
        self::assertCount($variants1->count(), $variants2);
        self::assertEquals($variants1->first()->id, $variants2->first()->id);
    }

    /**
     * Test that createVariantQuery does not use duplicateOf product ID when available
     */
    public function testCreateVariantQueryDoesNotUseDuplicateOfId(): void
    {
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $originalProduct = $productFixture->getElement('rad-hoodie');
        self::assertNotNull($originalProduct);

        // Create a duplicate product
        $duplicateProduct = new Product();
        $duplicateProduct->id = 999998;
        $duplicateProduct->typeId = $originalProduct->typeId;
        $duplicateProduct->siteId = 1002; // Different site
        $duplicateProduct->duplicateOf = $originalProduct;

        // Use reflection to access private createVariantQuery method
        $reflection = new ReflectionClass(Product::class);
        $method = $reflection->getMethod('createVariantQuery');
        $method->setAccessible(true);

        /** @var VariantQuery $query */
        $query = $method->invoke(null, $duplicateProduct);

        // Use reflection to check the query's ownerId and siteId
        $queryReflection = new ReflectionClass($query);
        $ownerIdProperty = $queryReflection->getProperty('ownerId');
        $ownerIdProperty->setAccessible(true);
        $siteIdProperty = $queryReflection->getProperty('siteId');
        $siteIdProperty->setAccessible(true);

        // Should use the original product's ID and siteId
        self::assertNotEquals($originalProduct->id, $ownerIdProperty->getValue($query));
        self::assertNotEquals($originalProduct->siteId, $siteIdProperty->getValue($query));
    }

    /**
     * Test includeDisabled parameter
     */
    public function testGetVariantsIncludeDisabledParameter(): void
    {
        /** @var ProductFixture $productFixture */
        $productFixture = $this->tester->grabFixture('products');
        $product = $productFixture->getElement('hypercolor-tshirt');
        self::assertNotNull($product);

        // Create a disabled variant
        $disabledVariant = new Variant();
        $disabledVariant->title = 'Disabled Variant';
        $disabledVariant->sku = 'disabled-variant-sku';
        $disabledVariant->enabled = false;
        $disabledVariant->setOwner($product);

        // Add to product's variants
        $variants = $product->getVariants()->all();
        $variants[] = $disabledVariant;
        $product->setVariants($variants);

        // Test without includeDisabled (default)
        $enabledVariants = $product->getVariants(false);
        foreach ($enabledVariants as $variant) {
            self::assertTrue($variant->enabled);
        }

        // Test with includeDisabled
        $allVariants = $product->getVariants(true);
        $hasDisabledVariant = false;
        foreach ($allVariants as $variant) {
            if (!$variant->enabled) {
                $hasDisabledVariant = true;
                break;
            }
        }
        self::assertTrue($hasDisabledVariant);
    }

    /**
     * Tests every combination of the nullable $includeDisabled parameter against the
     * NestedElementsController detection introduced alongside the signature change.
     * Also asserts that the internal $_variants collection is never mutated by the filter.
     *
     * @dataProvider getVariantsNullableIncludeDisabledDataProvider
     */
    public function testGetVariantsNullableIncludeDisabled(?bool $includeDisabled, bool $useNestedElementsController, int $expectedCount): void
    {
        $originalController = Craft::$app->controller;

        try {
            if ($useNestedElementsController) {
                $mockController = $this->getMockBuilder(NestedElementsController::class)
                    ->disableOriginalConstructor()
                    ->getMock();
                Craft::$app->controller = $mockController;
            }

            $product = new Product();
            $product->typeId = 2000;

            $enabled = new Variant();
            $enabled->enabled = true;
            $enabled->sku = 'enabled-sku';

            $disabled = new Variant();
            $disabled->enabled = false;
            $disabled->sku = 'disabled-sku';

            $product->setVariants([$enabled, $disabled]);

            $result = $product->getVariants($includeDisabled);
            self::assertCount($expectedCount, $result);

            // The internal collection must never be mutated by the filter —
            // regardless of which parameter was passed, all set variants must be retained.
            $reflection = new ReflectionClass($product);
            $variantsProperty = $reflection->getProperty('_variants');
            $variantsProperty->setAccessible(true);

            /** @var VariantCollection $internalVariants */
            $internalVariants = $variantsProperty->getValue($product);
            self::assertInstanceOf(VariantCollection::class, $internalVariants);
            self::assertCount(2, $internalVariants, '_variants must retain all variants regardless of the filter applied');

        } finally {
            // Clean up so the controller state does not leak into subsequent tests
            Craft::$app->controller = $originalController;
        }
    }

    /**
     * @return array<string, array{includeDisabled: bool|null, useNestedElementsController: bool, expectedCount: int}>
     */
    public function getVariantsNullableIncludeDisabledDataProvider(): array
    {
        return [
            // null resolves to false when no NestedElementsController is active
            'null-no-controller-excludes-disabled' => [
                'includeDisabled' => null,
                'useNestedElementsController' => false,
                'expectedCount' => 1,
            ],
            // null resolves to true when NestedElementsController is the active controller
            'null-nested-elements-controller-includes-disabled' => [
                'includeDisabled' => null,
                'useNestedElementsController' => true,
                'expectedCount' => 2,
            ],
            // Explicit false must override the NestedElementsController detection
            'explicit-false-with-nested-elements-controller' => [
                'includeDisabled' => false,
                'useNestedElementsController' => true,
                'expectedCount' => 1,
            ],
            // Explicit true must work even without a special controller
            'explicit-true-without-controller' => [
                'includeDisabled' => true,
                'useNestedElementsController' => false,
                'expectedCount' => 2,
            ],
        ];
    }
}
