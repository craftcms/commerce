<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\enums\LineItemType;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\test\mockclasses\Purchasable;
use craft\errors\SiteNotFoundException;
use craft\helpers\Json;
use craftcommercetests\fixtures\ProductFixture;
use craftcommercetests\fixtures\SalesFixture;
use yii\base\InvalidConfigException;

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
        $lineItem->setPromotionalPrice(1.114);
        $lineItem->qty = 2;

        self::assertSame(1.24, $lineItem->getPrice());
        self::assertSame(1.11, $lineItem->getPromotionalPrice());
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
        self::assertSame(0.0, $lineItem->getPromotionalAmount());
        self::assertSame('commerce_testing_unique_sku', $lineItem->sku);
        self::assertFalse($lineItem->getOnPromotion());
    }

    /**
     *
     */
    public function testAppliedSale(): void
    {
        $variant = Variant::find()->sku('rad-hood')->one();
        $lineItem = new LineItem();
        $lineItem->populateFromPurchasable($variant);

        self::assertSame(123.99, round($lineItem->price, 2));
        self::assertSame(111.59, round($lineItem->salePrice, 2));
        self::assertSame(12.40, round($lineItem->getPromotionalAmount(), 2));
        self::assertTrue($lineItem->getOnPromotion());
    }

    /**
     *
     */
    public function testSetOptions(): void
    {
        $options = [
            'foo' => 'bar',
            'numFoo' => 999,
            'emoji' => '❌',
        ];
        $jsonOptions = Json::encode($options);
        $lineItem = new LineItem();

        $output = [
            'foo' => 'bar',
            'numFoo' => 999,
            'emoji' => ':x:',
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

    /**
     * @return void
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @since 5.1.0
     */
    public function testIsPromotableProperty(): void
    {
        $variant = Variant::find()->sku('hct-blue')->one();
        $lineItem = new LineItem();
        $lineItem->populateFromPurchasable($variant);

        // Manually set the property the make sure it doesn't do anything when it is a purchasable line item
        $lineItem->setIsPromotable(false);

        self::assertTrue($lineItem->getIsPromotable());
    }

    /**
     * @return void
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @since 5.1.0
     */
    public function testHasFreeShippingProperty(): void
    {
        $variant = Variant::find()->sku('hct-blue')->one();
        $lineItem = new LineItem();
        $lineItem->populate($variant);

        // Manually set the property the make sure it doesn't do anything when it is a purchasable line item
        $lineItem->setHasFreeShipping(true);

        self::assertFalse($lineItem->getHasFreeShipping());
    }

    /**
     * @return void
     * @since 5.1.0
     */
    public function testCustomLineItem(): void
    {
        $lineItem = new LineItem();
        $lineItem->type = LineItemType::Custom;
        $lineItem->description = 'Custom';
        $lineItem->setSku('custom-sku');
        $lineItem->setPrice(10.00);
        $lineItem->qty = 2;
        $lineItem->setIsPromotable(false);
        $lineItem->setHasFreeShipping(true);

        $order = new Order();
        $order->number = Plugin::getInstance()->getCarts()->generateCartNumber();

        $order->setLineItems([$lineItem]);

        self::assertEquals(20.00, $order->getTotal());
    }
}
