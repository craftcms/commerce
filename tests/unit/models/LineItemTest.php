<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\test\mockclasses\Purchasable;
use craft\helpers\Json;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\SalesFixture;

/**
 * LineItemTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class LineItemTest extends Unit
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

    /**
     *
     */
    public function testPriceRounding(): void
    {
        $lineItem = new LineItem();
        $lineItem->setPrice(1.239);
        $lineItem->setSalePrice(1.114);
        $lineItem->qty = 2;

        self::assertSame(1.24, $lineItem->getPrice());
        self::assertSame(1.11, $lineItem->getSalePrice());
        self::assertSame(2.22, $lineItem->getSubtotal());
    }

    /**
     *
     */
    public function testPopulateFromPurchasable(): void
    {
        $purchasable = new Purchasable();
        $lineItem = new LineItem();
        $lineItem->populateFromPurchasable($purchasable);

        self::assertSame(25.10, $lineItem->price);
        self::assertSame(25.10, $lineItem->salePrice);
        self::assertSame(0.0, $lineItem->saleAmount);
        self::assertSame('commerce_testing_unique_sku', $lineItem->sku);
        self::assertFalse($lineItem->getOnSale());
    }

    /**
     *
     */
    public function testAppliedSale(): void
    {
        $variant = Variant::find()->sku('rad-hood')->one();
        $lineItem = new LineItem();
        $lineItem->populateFromPurchasable($variant);

        self::assertSame(123.99, $lineItem->price);
        self::assertSame(111.59, $lineItem->salePrice);
        self::assertSame(12.40, $lineItem->saleAmount);
        self::assertTrue($lineItem->getOnSale());
    }

    /**
     *
     */
    public function testSetOptions(): void
    {
        $options = [
            'foo' => 'bar',
            'numFoo' => 999,
            'emoji' => '❌'
        ];
        $jsonOptions = Json::encode($options);
        $lineItem = new LineItem();

        $output = [
            'foo' => 'bar',
            'numFoo' => 999,
            'emoji' => ':x:'
        ];

        // TODO change this when set options for emojis is refactored #COM-46
        $lineItem->setOptions($options);
        if (Craft::$app->getDb()->getSupportsMb4()) {
            self::assertSame($options, $lineItem->getOptions());
        } else {
            self::assertSame($output, $lineItem->getOptions());
        }

        $lineItem->setOptions($jsonOptions);
        if (Craft::$app->getDb()->getSupportsMb4()) {
            self::assertSame($options, $lineItem->getOptions());
        } else {
            self::assertSame($output, $lineItem->getOptions());
        }
    }

    /**
     *
     */
    public function testConsistentOptionsSignatures(): void
    {
        $options = ['Larry' => 'David'];
        $lineItem1 = new LineItem();
        $lineItem2 = new LineItem();

        $lineItem1->setOptions($options);
        $lineItem2->setOptions($options);

        self::assertSame($lineItem1->getOptionsSignature(), $lineItem2->getOptionsSignature());
    }

    /**
     *
     */
    public function testUniqueOptionSignatures(): void
    {
        $lineItem = new LineItem();
        $lineItem->setOptions(['foo' => 1]);
        $signature = $lineItem->getOptionsSignature();

        $lineItem->setOptions(['foo' => 2]);

        self::assertNotSame($signature, $lineItem->getOptionsSignature());
    }
}