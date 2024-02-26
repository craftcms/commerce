<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\adjusters;

use Codeception\Test\Unit;
use craft\commerce\adjusters\Discount;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\Discount as DiscountModel;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\records\Discount as DiscountRecord;
use craft\commerce\services\Discounts;
use craft\helpers\ArrayHelper;

/**
 * DiscountTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.5
 */
class DiscountTest extends Unit
{
    /**
     * @var Plugin|null
     */
    public ?Plugin $pluginInstance;

    /**
     * @var string|null
     */
    public ?string $originalEdition;

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();
    }

    /**
     * @dataProvider adjustDataProvider
     */
    public function testAdjust($lineItemData, $discountData, $expected): void
    {
        // Create discount model
        $discount = new DiscountModel();

        foreach ($discountData as $prop => $discountDatum) {
            $discount->{$prop} = $discountDatum;
        }

        // Mock discounts service
        $this->pluginInstance->set('discounts', $this->make(Discounts::class, [
            'getAllActiveDiscounts' => function($o) use ($discount) {
                return [$discount];
            },
            'matchOrder' => function($o, $d) {
                return true;
            },
        ]));

        $order = new Order();

        $lineItems = [];
        foreach ($lineItemData as $item) {
            $lineItem = $this->make(LineItem::class, [
                'qty' => $item['qty'],
                'salePrice' => $item['salePrice'],
                'getPurchasable' => function() use ($item) {
                    return $item['purchasable'];
                },
            ]);
            $lineItems[] = $lineItem;
        }

        $order->setLineItems($lineItems);

        $discountAdjuster = $this->make(Discount::class, []);

        $adjustments = $discountAdjuster->adjust($order);
        $order->setAdjustments($adjustments);

        self::assertCount(count($expected['adjustments']), $adjustments, 'Total number of adjustments');

        foreach ($expected['adjustments'] as $index => $item) {
            /** @var OrderAdjustment|null $adj */
            $adj = ArrayHelper::firstWhere($adjustments, 'description', $item['description']);
            self::assertNotNull($adj);
            self::assertEquals($item['amount'], $adj->amount, 'Adjustment amount');
            self::assertEquals($item['type'], $adj->type, 'Adjustment type');
        }

        self::assertEquals($expected['orderTotalPrice'], $order->getTotalPrice(), 'Order total price');
        self::assertEquals($expected['orderTotalDiscount'], $order->getTotalDiscount(), 'Order total discount');
    }

    /**
     * @return array[]
     */
    public function adjustDataProvider(): array
    {
        $orderLevelDiscount = [
            'name' => 'Order Level',
            'description' => 'Order level discount',
            'allPurchasables' => true,
            'allCategories' => true,
            'stopProcessing' => false,
            'baseDiscount' => -10,
            'baseDiscountType' => DiscountRecord::BASE_DISCOUNT_TYPE_VALUE,
        ];

        $lineItemPromotable = [
            'salePrice' => 100,
            'qty' => 1,
            'purchasable' => new class() extends Purchasable {
                public function getPrice(): float
                {
                    return 100;
                }

                public function getSku(): string
                {
                    return 'testing';
                }

                public function getIsPromotable(): bool
                {
                    return true;
                }
            },
        ];

        $lineItemNonPromotable = [
            'salePrice' => 100,
            'qty' => 1,
            'purchasable' => new class() extends Purchasable {
                public function getPrice(): float
                {
                    return 100;
                }

                public function getSku(): string
                {
                    return 'testingNon';
                }

                public function getIsPromotable(): bool
                {
                    return false;
                }
            },
        ];

        return [
            // Example 1) 10 base discount (order level) with promotable line item
            [
                [ // Line Items
                    $lineItemPromotable,
                ],
                $orderLevelDiscount,
                [
                    'adjustments' => [
                        [
                            'type' => 'discount',
                            'amount' => $orderLevelDiscount['baseDiscount'],
                            'description' => $orderLevelDiscount['description'],
                        ],
                    ],
                    'orderTotalPrice' => 90,
                    'orderTotalDiscount' => $orderLevelDiscount['baseDiscount'],
                ],
            ],
            // Example 2) 10 base discount (order level) with non-promotable line item
            [
                [ // Line Items
                    $lineItemNonPromotable,
                ],
                $orderLevelDiscount,
                [
                    'adjustments' => [
                    ],
                    'orderTotalPrice' => 100,
                    'orderTotalDiscount' => 0,
                ],
            ],
            // Example 3) 10 base discount (order level) with both promotable and non-promotable line items
            [
                [ // Line Items
                    $lineItemNonPromotable,
                    $lineItemPromotable,
                ],
                array_merge($orderLevelDiscount, ['baseDiscount' => -110]),
                [
                    'adjustments' => [
                        [
                            'type' => 'discount',
                            'amount' => -100,
                            'description' => $orderLevelDiscount['description'],
                        ],
                    ],
                    'orderTotalPrice' => 100,
                    'orderTotalDiscount' => -100,
                ],
            ],
        ];
    }
}
