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

/**
 * ProductTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class ProductTest extends Unit
{
    /**
     * @group Product
     */
    public function testProductPopulationAndValidation()
    {
        $commerce = Plugin::getInstance();

        $product = new Product();
        $product->enabled = false;
        $product->title = 'test';
        $product->typeId = 1;

        $variant = new Variant();
        $variant->title = 'variant 1';
        $product->setVariants([$variant]);

        $product->validate();

        self::assertCount(0, count($product->getErrors()));
    }
}