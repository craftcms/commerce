<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\helpers;

use Codeception\Test\Unit;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Product as ProductHelper;
use craft\web\Request;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;
use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;

/**
 * ProductHelperTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.1.1
 */
class ProductHelperTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    /**
     * @param string $productFixtureHandle
     * @return void
     * @throws NotFoundHttpException
     * @dataProvider productFromPostDataProvider
     */
    public function testProductFromPost(string $productFixtureHandle): void
    {
        /** @var ProductFixture $productsFixture */
        $productsFixture = $this->tester->grabFixture('products');
        /** @var Product $productFixtureElement */
        $productFixtureElement = $productsFixture->getElement($productFixtureHandle);

        $request = new Request();
        $request->setBodyParams([
            'productId' => $productFixtureElement->id,
            'siteId' => $productFixtureElement->siteId,
        ]);

        $product = ProductHelper::productFromPost($request);

        self::assertEquals($productFixtureElement->id, $product->id);
        self::assertEquals($productFixtureElement->siteId, $product->siteId);
    }

    public function productFromPostDataProvider(): array
    {
        return [
            'from-post-1' => [
                'rad-hoodie',
            ],
        ];
    }
}
