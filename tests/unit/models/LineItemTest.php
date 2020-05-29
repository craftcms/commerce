<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\test\mockclasses\Purchasable;
use craft\helpers\Json;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\SalePurchasablesFixture;
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
            'salePurchasables' => [
                'class' => SalePurchasablesFixture::class,
            ]
        ];
    }

    public function testPriceRounding()
    {
        $lineItem = new LineItem();
        $lineItem->setPrice(1.239);
        $lineItem->setSalePrice(1.114);
        $lineItem->qty = 2;

        $this->assertSame(1.24, $lineItem->getPrice());
        $this->assertSame(1.11, $lineItem->getSalePrice());
        $this->assertSame(2.22, $lineItem->getSubtotal());
    }

    public function testPopulateFromPurchasable()
    {
        $purchasable = new Purchasable();
        $lineItem = new LineItem();
        $lineItem->populateFromPurchasable($purchasable);

        $this->assertSame(25.10, $lineItem->price);
        $this->assertSame(25.10, $lineItem->salePrice);
        $this->assertSame(0.0, $lineItem->saleAmount);
        $this->assertSame('commerce_testing_unique_sku', $lineItem->sku);
        $this->assertSame(false, $lineItem->getOnSale());
    }

    public function testAppliedSale()
    {
        $variant = Variant::find()->sku('rad-hood')->one();
        $lineItem = new LineItem();
        $lineItem->populateFromPurchasable($variant);

        $this->assertSame(123.99, $lineItem->price);
        $this->assertSame(111.59, $lineItem->salePrice);
        $this->assertSame(12.40, $lineItem->saleAmount);
        $this->assertSame(true, $lineItem->getOnSale());
    }

    public function testSetOptions()
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


        // TODO change this when set options for emojis is refactored
        $lineItem->setOptions($options);
        if (Craft::$app->getDb()->getSupportsMb4()) {
            $this->assertSame($options, $lineItem->getOptions());
        } else {
            $this->assertSame($output, $lineItem->getOptions());
        }

        $lineItem->setOptions($jsonOptions);
        if (Craft::$app->getDb()->getSupportsMb4()) {
            $this->assertSame($options, $lineItem->getOptions());
        } else {
            $this->assertSame($output, $lineItem->getOptions());
        }
    }

    public function testConsistentOptionsSignatures()
    {
        $options = ['Larry' => 'David'];
        $lineItem1 = new LineItem();
        $lineItem2 = new LineItem();

        $lineItem1->setOptions($options);
        $lineItem2->setOptions($options);

        $this->assertSame($lineItem1->getOptionsSignature(), $lineItem2->getOptionsSignature());
    }

    public function testUniqueOptionSignatures()
    {
        $lineItem = new LineItem();
        $lineItem->setOptions(['foo' => 1]);
        $signature = $lineItem->getOptionsSignature();

        $lineItem->setOptions(['foo' => 2]);

        $this->assertNotSame($signature, $lineItem->getOptionsSignature());
    }
}