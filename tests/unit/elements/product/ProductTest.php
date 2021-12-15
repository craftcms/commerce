<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\SalesFixture;

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
            ]
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
                ]
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
                ]
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
                ]
            ],
        ];
    }
}
