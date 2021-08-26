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
}
