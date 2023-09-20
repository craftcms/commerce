<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\commerce\services\CatalogPricingRules;
use craft\commerce\services\Sales;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\SalesFixture;

/**
 * PricingCatalogTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PricingCatalogTest extends Unit
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

    public function testVariantPricing()
    {
        $variant = Variant::find()->sku('rad-hood')->one();

        Plugin::getInstance()->set('catalogPricingRules', $this->make(CatalogPricingRules::class, [
            'canUseCatalogPricingRules' => function() {
                self::atLeastOnce();
                return true;
            },
        ]));

        Plugin::getInstance()->set('sales', $this->make(Sales::class, [
            'getAllSales' => function () {
                self::never();
                return [];
            }
        ]));

        self::assertEquals(123.99, $variant->getPrice());
        self::assertEquals(null, $variant->getPromotionalPrice());
        self::assertEquals(123.99, $variant->getSalePrice());
    }
}
