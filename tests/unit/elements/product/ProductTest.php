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
     * @group Product
     * @dataProvider productMassAssignmentDataProvider
     */
    public function testProductMassAssignment(array $data, array $checkKeys): void
    {
        $data += ['class' => Product::class];
        $product = \Craft::createObject($data);

        foreach ($checkKeys as $checkKey) {
            if ($checkKey === 'variants') {
                self::assertCount(count($data[$checkKey]), $product->getVariants());
                $variant = $product->getVariants()[0];

                foreach (array_keys($data[$checkKey][0]) as $variantKey) {
                    self::assertEquals($data[$checkKey][0][$variantKey], $variant->$variantKey);
                }
                continue;
            }

            self::assertEquals($data[$checkKey], $product->$checkKey);
        }
    }

    public function productMassAssignmentDataProvider(): array
    {
        return [
            'just-properties' => [
                [
                    'title' => 'Test Product',
                    'typeId' => 2000,
                    'enabled' => true,
                    'variants' => [
                        [
                            'title' => 'Test Variant',
                            'basePrice' => 123,
                            'sku' => '123',
                            'enabled' => true,
                        ],
                    ],
                ],
                ['title', 'typeId', 'enabled', 'variants'],
            ],
            'props-and-custom-fields' => [
                [
                    'title' => 'Test Product',
                    'typeId' => 2000,
                    'enabled' => true,
                    'variants' => [
                        [
                            'title' => 'Test Variant',
                            'basePrice' => 123,
                            'sku' => '123',
                            'enabled' => true,
                            'myVariantHeadingField' => 'bar',
                        ],
                    ],
                    'myHeadingField' => 'foo',
                ],
                ['title', 'typeId', 'enabled', 'variants'],
            ],
        ];
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
        $defaultVariantId = null;
        foreach ($variantData as [$id, $price, $default, $enabled]) {
            $variant = new Variant();
            $variant->id = $id;
            $variant->title = sprintf('Test Variant #%s', $count);
            $variant->isDefault = $default;
            $defaultVariantId = $default ? $id : $defaultVariantId;
            $variant->enabled = $enabled;
            $variant->price = $price;

            $variants[] = $variant;
            $count++;
        }

        $product->setVariants($variants);
        if ($defaultVariantId) {
            $product->defaultVariantId = $defaultVariantId;
        }

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
                [[1001, 123, true, true], [1002, 456, false, true], [1003, 789, false, true]],
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
                [[1001, 123, false, false], [1002, 456, false, true], [1003, 789, true, true]],
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
                [[1001, 123, false, false], [1002, 456, true, false], [1003, 99, false, false]],
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
     * @dataProvider productSavedInSitesDataProvider
     */
    public function testProductSavedInSites(int $siteId, int $count): void
    {
        self::assertCount($count, Product::find()->siteId($siteId)->all());
    }

    public function productSavedInSitesDataProvider(): array
    {
        return [
            'primary' => [1, 2],
            'uk' => [1002, 3],
        ];
    }

    /**
     * @return void
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function testSaveProductAndVariants(): void
    {
        $product = new Product();
        $product->title = 'Test Product';
        $product->typeId = 2000;
        $product->slug = 'test-product';
        $product->enabled = true;
        $product->enabledForSite = true;
        $product->postDate = (new DateTime('now'));

        $variants = [];
        $variant = new Variant();
        $variant->title = 'Test Variant';
        $variant->slug = 'test-variant';
        $variant->sku = 'test-variant-sku';
        $variant->basePrice = 99.99;
        $variant->sortOrder = 0;
        $variant->width = null;
        $variant->height = null;
        $variant->length = null;
        $variant->weight = null;
        $variant->inventoryTracked = false;
        $variant->minQty = null;
        $variant->maxQty = null;
        $variant->isDefault = true;

        $variants[] = $variant;

        $variant = new Variant();
        $variant->title = 'Test Variant 2';
        $variant->slug = 'test-variant 2';
        $variant->sku = 'test-variant-sku2';
        $variant->basePrice = 100.99;
        $variant->sortOrder = 1;
        $variant->width = null;
        $variant->height = null;
        $variant->length = null;
        $variant->weight = null;
        $variant->inventoryTracked = false;
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
                'v.id',
            ])
            ->from(Table::VARIANTS . ' v')
            ->leftJoin(Table::PURCHASABLES . ' p', '[[p.id]] = [[v.id]]')
            ->where(['primaryOwnerId' => $product->id])
            ->andWhere(['p.sku' => 'test-variant-sku'])
            ->one();

        // Check the product object
        self::assertEquals($defaultVariantData['id'], $product->getDefaultVariant()->id);
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
        $variant->basePrice = 199.99;

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
