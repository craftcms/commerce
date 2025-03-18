<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\product;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craftcommercetests\fixtures\ProductFixture;

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
                        ]
                    ]
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
                        ]
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
}
