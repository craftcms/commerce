<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\enums\LineItemType;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craftcommercetests\fixtures\ProductFixture;
use UnitTester;

/**
 * OrderDeleteTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class OrderDeleteTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

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

    /**
     * A completed order containing a custom line item couldn't be deleted: `Order::afterDelete()`
     * calls `LineItem::getPurchasable()` on every line item captured in `beforeDelete()` to
     * refresh the stock cache, and `getPurchasable()` throws for `LineItemType::Custom` line
     * items, rolling back the delete transaction.
     *
     * https://github.com/craftcms/commerce/issues/4371
     *
     * @param LineItemType[] $lineItemTypes
     * @return void
     * @dataProvider lineItemTypesDataProvider
     */
    public function testDeleteCompletedOrder(array $lineItemTypes): void
    {
        $order = new Order();
        $order->number = Plugin::getInstance()->getCarts()->generateCartNumber();

        $lineItems = array_map(fn(LineItemType $type) => $this->_createLineItem($order, $type), $lineItemTypes);
        $order->setLineItems($lineItems);

        self::assertTrue($order->markAsComplete());

        self::assertTrue(Craft::$app->getElements()->deleteElement($order));
    }

    /**
     * @return array
     */
    public function lineItemTypesDataProvider(): array
    {
        return [
            'purchasable line items only' => [[LineItemType::Purchasable]],
            'custom line items only' => [[LineItemType::Custom]],
            'mixture of purchasable and custom line items' => [[LineItemType::Purchasable, LineItemType::Custom]],
        ];
    }

    /**
     * @param Order $order
     * @param LineItemType $type
     * @return LineItem
     */
    private function _createLineItem(Order $order, LineItemType $type): LineItem
    {
        if ($type === LineItemType::Custom) {
            return Plugin::getInstance()->getLineItems()->create($order, [
                'sku' => 'custom-sku',
                'description' => 'Custom',
                'price' => 10,
                'qty' => 1,
                'taxCategoryId' => Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id,
                'shippingCategoryId' => Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($order->storeId)->id,
            ], LineItemType::Custom);
        }

        $variant = Variant::find()->sku('hct-blue')->one();
        self::assertNotNull($variant);

        return Plugin::getInstance()->getLineItems()->create($order, [
            'purchasableId' => $variant->id,
            'qty' => 1,
        ]);
    }
}
