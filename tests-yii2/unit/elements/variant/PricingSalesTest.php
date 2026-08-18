<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\variant;

use Codeception\Test\Unit;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\commerce\services\CatalogPricingRules;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\SalesFixture;

/**
 * PricingSalesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class PricingSalesTest extends Unit
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
            'sales' => [
                'class' => SalesFixture::class,
            ],
        ];
    }

    public function testVariantPricing()
    {
        $variant = Variant::find()->sku('rad-hood')->one();

        Plugin::getInstance()->set('catalogPricingRules', $this->make(CatalogPricingRules::class, [
            'canUseCatalogPricingRules' => function() {
                self::atLeastOnce();
                return false;
            },
        ]));

        self::assertEquals(123.99, $variant->getPrice());
        self::assertEquals(111.59, $variant->getPromotionalPrice());
        self::assertEquals(111.59, $variant->getSalePrice());
    }
}
