<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\elements\Variant;
use craft\commerce\elements\VariantCollection;
use craft\elements\ElementCollection;
use craftcommercetests\fixtures\ProductFixture;

/**
 * VariantCollectionTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class VariantCollectionTest extends Unit
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

    public function testVariantQueryCollect()
    {
        $collection = Variant::find()->limit(4)->collect();

        self::assertInstanceOf(VariantCollection::class, $collection);
        self::assertInstanceOf(ElementCollection::class, $collection);
    }
}
