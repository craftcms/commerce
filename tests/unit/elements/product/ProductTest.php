<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\product;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\db\Query;
use craftcommercetests\fixtures\ProductFixture;
use DateTime;

/**
 * ProductTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.3
 */
class ProductTest extends Unit
{
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
     * @group Product
     */
    public function testProductPopulationAndValidation(): void
    {
        $product = new Product();
        $product->enabled = false;
        $product->title = 'test';
        $product->typeId = 2000;

        $variant = new Variant();
        $variant->title = 'variant 1';
        $product->setVariants([$variant]);

        $product->validate();

        self::assertCount(0, $product->getErrors());
    }

    /**
     * @dataProvider productVariantMethodsDataProvider
     */
    public function testProductVariantMethods(int $productTypeId, array $variantData, array $expected): void
    {
        $product = new Product();
        $product->enabled = true;
        $product->typeId = $productTypeId;
        $product->title = 'Test Product';

        $variants = [];
        $count = 1;
        foreach ($variantData as [$price, $default, $enabled]) {
            $variant = new Variant();
            $variant->title = sprintf('Test Variant #%s', $count);
            $variant->isDefault = $default;
            $variant->enabled = $enabled;
            $variant->price = $price;

            $variants[] = $variant;
            $count++;
        }

        $product->setVariants($variants);

        self::assertCount($expected['variantCount'], $product->getVariants(true));
        self::assertCount($expected['enabledVariantCount'], $product->getVariants());

        $defaultVariant = $product->getDefaultVariant(true);
        self::assertSame($expected['defaultVariantTitle'], $defaultVariant->title);

        $cheapestVariant = $product->getCheapestVariant(true);
        self::assertSame($expected['cheapestVariantTitle'], $cheapestVariant->title);

        $defaultEnabledVariant = $product->getDefaultVariant();
        self::assertSame($expected['defaultEnabledVariantTitle'], $defaultEnabledVariant->title ?? null);

        $cheapestEnabledVariant = $product->getCheapestVariant();
        self::assertSame($expected['cheapestEnabledVariantTitle'], $cheapestEnabledVariant->title ?? null);
    }

    /**
     * @return array
     */
    public function productVariantMethodsDataProvider(): array
    {
        return [
            'All Enabled' => [
                2001,
                [[123, true, true], [456, false, true], [789, false, true]],
                [
                    'variantCount' => 3,
                    'enabledVariantCount' => 3,
                    'cheapestVariantTitle' => 'Test Variant #1',
                    'defaultVariantTitle' => 'Test Variant #1',
                    'cheapestEnabledVariantTitle' => 'Test Variant #1',
                    'defaultEnabledVariantTitle' => 'Test Variant #1',
                ],
            ],
            'One Disabled' => [
                2001,
                [[123, false, false], [456, false, true], [789, true, true]],
                [
                    'variantCount' => 3,
                    'enabledVariantCount' => 2,
                    'cheapestVariantTitle' => 'Test Variant #1',
                    'defaultVariantTitle' => 'Test Variant #3',
                    'cheapestEnabledVariantTitle' => 'Test Variant #2',
                    'defaultEnabledVariantTitle' => 'Test Variant #3',
                ],
            ],
            'All Disabled' => [
                2001,
                [[123, false, false], [456, true, false], [99, false, false]],
                [
                    'variantCount' => 3,
                    'enabledVariantCount' => 0,
                    'cheapestVariantTitle' => 'Test Variant #3',
                    'defaultVariantTitle' => 'Test Variant #2',
                    'cheapestEnabledVariantTitle' => null,
                    'defaultEnabledVariantTitle' => null,
                ],
            ],
        ];
    }

    /**
     * @return void
     */
    public function testSaveProductAndVariants(): void
    {
        $product = new Product();
        $product->title = 'Test Product';
        $product->typeId = 2000;
        $product->slug = 'test-product';
        $product->enabled = true;
        $product->enabledForSite = true;
        $product->availableForPurchase = true;
        $product->promotable = true;
        $product->postDate = (new DateTime('now'));
        $product->shippingCategoryId = 101;
        $product->taxCategoryId = 101;

        $variants = [];
        $variant = new Variant();
        $variant->title = 'Test Variant';
        $variant->slug = 'test-variant';
        $variant->sku = 'test-variant-sku';
        $variant->price = 99.99;
        $variant->sortOrder = 0;
        $variant->width = null;
        $variant->height = null;
        $variant->length = null;
        $variant->weight = null;
        $variant->stock = null;
        $variant->hasUnlimitedStock = true;
        $variant->minQty = null;
        $variant->maxQty = null;
        $variant->isDefault = true;

        $variants[] = $variant;

        $variant = new Variant();
        $variant->title = 'Test Variant 2';
        $variant->slug = 'test-variant 2';
        $variant->sku = 'test-variant-sku2';
        $variant->price = 100.99;
        $variant->sortOrder = 1;
        $variant->width = null;
        $variant->height = null;
        $variant->length = null;
        $variant->weight = null;
        $variant->stock = null;
        $variant->hasUnlimitedStock = true;
        $variant->minQty = null;
        $variant->maxQty = null;
        $variant->isDefault = false;
        $variants[] = $variant;

        $product->setVariants($variants);

        \Craft::$app->getElements()->saveElement($product, false);

        // Check default data when the variant is saved as part of the product save
        $productData = (new Query())
            ->select([
                'defaultVariantId',
                'defaultSku',
                'defaultPrice',
                'defaultWidth',
                'defaultHeight',
                'defaultLength',
                'defaultWeight',
            ])
            ->from(Table::PRODUCTS)
            ->where(['id' => $product->id])
            ->one();

        $defaultVariantData = (new Query())
            ->select([
                'id',
            ])
            ->from(Table::VARIANTS)
            ->where(['productId' => $product->id])
            ->andWhere(['sku' => 'test-variant-sku'])
            ->one();

        // Check the product object
        self::assertEquals($defaultVariantData['id'], $product->defaultVariantId);
        self::assertEquals('test-variant-sku', $product->defaultSku);
        self::assertEquals(99.99, $product->defaultPrice);
        self::assertEquals(0, $product->defaultWidth);
        self::assertEquals(0, $product->defaultHeight);
        self::assertEquals(0, $product->defaultLength);
        self::assertEquals(0, $product->defaultWeight);

        // Check the product data in the database
        self::assertEquals($defaultVariantData['id'], $productData['defaultVariantId']);
        self::assertEquals('test-variant-sku', $productData['defaultSku']);
        self::assertEquals(99.99, $productData['defaultPrice']);
        self::assertEquals(0, $productData['defaultWidth']);
        self::assertEquals(0, $productData['defaultHeight']);
        self::assertEquals(0, $productData['defaultLength']);
        self::assertEquals(0, $productData['defaultWeight']);

        // Make changes and independently save the default variant to check the product data is updated
        $variant = $product->getDefaultVariant();
        $variant->setSku('test-variant-sku-updated');
        $variant->price = 199.99;

        \Craft::$app->getElements()->saveElement($variant, false);

        $newProductData = (new Query())
            ->select([
                'defaultVariantId',
                'defaultSku',
                'defaultPrice',
                'defaultWidth',
                'defaultHeight',
                'defaultLength',
                'defaultWeight',
            ])
            ->from(Table::PRODUCTS)
            ->where(['id' => $product->id])
            ->one();

        self::assertEquals($defaultVariantData['id'], $newProductData['defaultVariantId']);
        self::assertEquals('test-variant-sku-updated', $newProductData['defaultSku']);
        self::assertEquals(199.99, $newProductData['defaultPrice']);
        self::assertEquals(0, $newProductData['defaultWidth']);
        self::assertEquals(0, $newProductData['defaultHeight']);
        self::assertEquals(0, $newProductData['defaultLength']);
        self::assertEquals(0, $newProductData['defaultWeight']);

        // Remove the product
        \Craft::$app->getElements()->deleteElementById($product->id, Product::class, null, true);
    }
}
