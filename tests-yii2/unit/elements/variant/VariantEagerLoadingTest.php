<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;

/**
 * VariantEagerLoadingTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.4.8
 */
class VariantEagerLoadingTest extends Unit
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

    public function testEagerLoadingMap(): void
    {
        $variants = Variant::find()->all();

        $handles = [
            'product' => ['elementType' => Product::class],
            'owner' => ['elementType' => Product::class],
            'primaryOwner' => ['elementType' => Product::class],
            'customField' => null,
        ];

        foreach ($handles as $handle => $value) {
            $map = Variant::eagerLoadingMap($variants, $handle);

            if (is_array($value)) {
                self::assertNotEmpty($map);
                foreach ($value as $key => $item) {
                    self::assertArrayHasKey($key, $map);
                    self::assertEquals($item, $map[$key]);
                }
            } else {
                self::assertEmpty($map);
            }
        }
    }
}
