<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Stub;
use Codeception\Test\Unit;
use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\services\Discounts;
use craft\commerce\test\mockclasses\Purchasable;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\User;
use craftcommercetests\fixtures\CategoriesFixture;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\DiscountsFixture;
use craftcommercetests\fixtures\ProductFixture;
use DateInterval;
use DateTime;
use DateTimeZone;
use UnitTester;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * DiscountsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class DiscountsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Discounts $discounts
     */
    protected Discounts $discounts;

    /**
     * @var User|null
     */
    private ?User $_user;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'discounts' => [
                'class' => DiscountsFixture::class,
            ],
            'customers' => [
                'class' => CustomerFixture::class,
            ],
            'products' => [
                'class' => ProductFixture::class,
            ],
            'categories' => [
                'class' => CategoriesFixture::class,
            ],
        ];
    }

    /**
     *
     */
    public function testOrderCouponAvailableWithInvalidCoupon(): void
    {
        $this->orderCouponAvailableTest(
            ['couponCode' => 'invalid_coupon'],
            false,
            'Coupon not valid.'
        );
    }

    /**
     *
     */
    public function testSuccessOrderCouponAvailable(): void
    {
        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => $this->_user->id],
            true,
            ''
        );
    }

    /**
     * @throws Exception
     */
    public function testExistingCouponNotEnabled(): void
    {
        // Set it to be disabled
        $this->updateOrderCoupon([
            'enabled' => false,
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => $this->_user->id],
            false,
            'Coupon not valid.'
        );
    }

    /**
     * @throws Exception
     */
    public function testOrderCouponExpired(): void
    {
        // Invalidate the coupon.... It's valid until sometime in the past.
        $this->updateOrderCoupon([
            'dateTo' => '2019-05-01 10:21:33',
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => $this->_user->id],
            false,
            'Discount is out of date.'
        );
    }

    /**
     * @throws Exception
     */
    public function testOrderCouponNotYetValid(): void
    {
        // Set the coupon to start in two days from now.
        $date = new DateTime('now');
        $date->add(new DateInterval('P2D'));
        $this->updateOrderCoupon([
            'dateFrom' => $date->format('Y-m-d H:i:s'),
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => $this->_user->id],
            false,
            'Discount is out of date.'
        );
    }

    /**
     * @throws Exception
     */
    public function testCouponThatHasBeenUsedTooMuch(): void
    {
        $this->updateOrderCoupon([
            'totalDiscountUses' => 2,
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => $this->_user->id],
            false,
            'Discount use has reached its limit.'
        );
    }

    /**
     * @throws Exception
     */
    public function testCouponWithUseLimitAndNoUserOnClient(): void
    {
        $this->updateOrderCoupon([
            'perUserLimit' => true,
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => null],
            false,
            'This coupon is for registered users and limited to 1 uses.'
        );
    }

    /**
     * @throws Exception
     */
    public function testCouponPerUserLimit(): void
    {
        $this->updateOrderCoupon([
            'perUserLimit' => '1',
        ]);

        Craft::$app->getDb()->createCommand()
            ->insert('{{%commerce_customer_discountuses}}', [
                'customerId' => $this->_user->id,
                'discountId' => $this->tester->grabFixture('discounts')['discount_with_coupon']['id'],
                'uses' => '1',
            ])->execute();

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => $this->_user->id],
            false,
            'This coupon is for registered users and limited to 1 uses.'
        );

        Craft::$app->getDb()->createCommand()->truncateTable(Table::CUSTOMER_DISCOUNTUSES)->execute();
    }

    /**
     * @throws Exception
     * @todo Replace stub with fixture data. #COM-54
     *
     */
    public function testCouponPerEmailLimit(): void
    {
        $this->updateOrderCoupon([
            'perEmailLimit' => '1',
        ]);

        Craft::$app->getDb()->createCommand()
            ->insert(Table::EMAIL_DISCOUNTUSES, [
                'email' => 'testing@craftcommerce.com',
                'discountId' => $this->tester->grabFixture('discounts')['discount_with_coupon']['id'],
                'uses' => '1',
            ])->execute();

        /** @var Order $order */
        $order = Stub::construct(
            Order::class,
            [['couponCode' => 'discount_1', 'customerId' => $this->_user->id]],
            ['getEmail' => 'testing@craftcommerce.com']
        );

        $explanation = '';
        $result = $this->discounts->orderCouponAvailable($order, $explanation);
        self::assertFalse($result);
        self::assertSame('This coupon is limited to 1 uses.', $explanation);

        Craft::$app->getDb()->createCommand()->truncateTable(Table::CUSTOMER_DISCOUNTUSES)->execute();
    }

    /**
     *
     */
    public function testLineItemMatchingSuccess(): void
    {
        $this->matchLineItems(
            ['couponCode' => null],
            ['qty' => 2, 'salePrice' => 10],
            ['allPurchasables' => true, 'allCategories' => true],
            [],
            true
        );
    }

    /**
     *
     */
    public function testLineItemMatchingSaleFail(): void
    {
        $this->matchLineItems(
            ['couponCode' => null],
            ['qty' => 2, 'price' => 15, 'salePrice' => 10],
            ['excludeOnSale' => true],
            [],
            false
        );
    }

    /**
     *
     */
    public function testLineItemMatchingIfNotPromotable(): void
    {
        $this->matchLineItems(
            ['couponCode' => null],
            ['qty' => 2, 'price' => 15],
            [],
            ['isPromotable' => false],
            false
        );
    }

    // TODO: More tests required. Like lineItemMatching category and purchasableIds based features. #COM-54

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testOrderCompleteHandler(): void
    {
        $discountId = $this->tester->grabFixture('discounts')['discount_with_coupon']['id'];

        // TODO: Update this test to create a full real order that saves. #COM-54
        /** @var Order $order */
        $order = $this->make(Order::class, [
            'getAdjustmentsByType' => function($type) use ($discountId) {
                $adjustment = new OrderAdjustment();
                $adjustment->sourceSnapshot = ['discountUseId' => $discountId];

                return [$adjustment];
            },
        ]);
        $order->couponCode = 'discount_1';
        $order->setCustomerId($this->_user->id);

        $this->updateOrderCoupon([
            'perUserLimit' => '0',
            'perEmailLimit' => '0',
        ]);

        $this->discounts->orderCompleteHandler($order);

        // Get thew new Total uses.
        $totalUses = (int)(new Query())
            ->select('totalDiscountUses')
            ->from('{{%commerce_discounts}}')
            ->where(['id' => $discountId])
            ->scalar();

        self::assertSame(1, $totalUses);

        // Get the Customer Discount Uses
        $customerUses = (new Query())
            ->select('*')
            ->from('{{%commerce_customer_discountuses}}')
            ->where(['customerId' => $this->_user->id, 'discountId' => $discountId, 'uses' => '1'])
            ->one();

        self::assertNotNull($customerUses);

        // Get the Email Discount Uses
        $customerEmail = $order->getCustomer()->email;
        $customerUses = (new Query())
            ->select('*')
            ->from('{{%commerce_email_discountuses}}')
            ->where(['email' => $customerEmail, 'discountId' => $discountId, 'uses' => '1'])
            ->one();

        self::assertNotNull($customerUses);

        // Coupon uses
        $couponUses = (new Query())
            ->select('uses')
            ->from(Table::COUPONS)
            ->where(['code' => 'discount_1'])
            ->scalar();

        self::assertEquals(1, $couponUses);
    }

    /**
     *
     */
    public function testVoidIfNoCouponCode(): void
    {
        $order = new Order(['couponCode' => null]);
        self::assertNull(
            $this->discounts->orderCompleteHandler($order)
        );
    }

    /**
     *
     */
    public function testVoidIfInvalidCouponCode(): void
    {
        $order = new Order(['couponCode' => 'i_dont_exist_as_coupon']);
        self::assertNull(
            $this->discounts->orderCompleteHandler($order)
        );
    }

    /**
     * @return void
     * @throws Exception
     * @throws \Random\RandomException
     */
    public function testEnsureSortOrder(): void
    {
        $ids = [];
        // Create dummy discount records
        for ($i = 1; $i <= 5; $i++) {
            $discount = new \craft\commerce\records\Discount();
            $discount->name = 'Dummy Discount ' . $i;
            // randomise the sort order
            $discount->sortOrder = $i + random_int(1, 15);
            $discount->enabled = true;
            $discount->allCategories = true;
            $discount->allPurchasables = true;
            $discount->percentageOffSubject = 'original';
            $discount->save();
            $ids[] = $discount->id;
        }

        $this->discounts->ensureSortOrder();

        // Check table directly
        $discountRows = (new Query())
            ->select(['id', 'sortOrder'])
            ->from(Table::DISCOUNTS)
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        for ($i = 0; $i < count($discountRows); $i++) {
            self::assertEquals($i + 1, $discountRows[$i]['sortOrder']);
        }

        // Check get all method
        $allDiscounts = $this->discounts->getAllDiscounts();
        for ($i = 0; $i < count($allDiscounts); $i++) {
            self::assertEquals($i + 1, $allDiscounts[$i]->sortOrder);
        }

        // delete temp records
        foreach ($ids as $id) {
            $this->discounts->deleteDiscountById($id);
        }
    }

    /**
     * @param array|false $attributes
     * @param int $count
     * @return void
     * @throws \Exception
     * @dataProvider gatAllActiveDiscountsDataProvider
     */
    public function testGetAllActiveDiscounts(array|false $attributes, int $count, array $discounts): void
    {
        if (!empty($discounts)) {
            foreach ($discounts as &$discount) {
                $emailUses = $discount['_emailUses'] ?? [];

                if (isset($discount['purchasableIds'])) {
                    $discount['purchasableIds'] = Variant::find()->sku($discount['purchasableIds'])->ids();
                }

                if (isset($discount['categoryIds'])) {
                    $discount['categoryIds'] = Category::find()->slug($discount['categoryIds'])->ids();
                }

                $discountModel = Craft::createObject([
                    'class' => Discount::class,
                    'attributes' => $discount,
                ]);
                Plugin::getInstance()->getDiscounts()->saveDiscount($discountModel);
                $discount = $discountModel->id;

                if ($discountModel->totalDiscountUses > 0) {
                    Craft::$app->getDb()->createCommand()
                        ->update(Table::DISCOUNTS, [
                            'totalDiscountUses' => $discountModel->totalDiscountUses,
                        ], [
                            'id' => $discountModel->id,
                        ])
                        ->execute();
                }

                if (!empty($emailUses)) {
                    $emailUses = collect($emailUses)->map(fn($uses, $email) => [$email, $discountModel->id, $uses])->all();
                    Craft::$app->getDb()->createCommand()
                        ->batchInsert(Table::EMAIL_DISCOUNTUSES, ['email', 'discountId', 'uses'], $emailUses)
                        ->execute();
                }
            }
        }

        if ($attributes === false) {
            $activeDiscounts = $this->discounts->getAllActiveDiscounts();
        } else {
            $order = new Order(array_diff_key($attributes, array_flip(['_lineItems'])));

            if (isset($attributes['_lineItems'])) {
                $lineItems = [];
                foreach ($attributes['_lineItems'] as $sku => $qty) {
                    $variant = Variant::find()->sku($sku)->one();
                    $lineItems[] = Plugin::getInstance()->getLineItems()->createLineItem($order, $variant->id, [], $qty);
                }
                $order->setLineItems($lineItems);
            }

            $activeDiscounts = $this->discounts->getAllActiveDiscounts($order);
        }

        if ($count > 0) {
            self::assertCount($count, $activeDiscounts);
            self::assertNotEmpty($activeDiscounts);
        } else {
            self::assertEmpty($activeDiscounts);
        }

        // Tidy up the discounts
        if (!empty($discounts)) {
            foreach ($discounts as $discountId) {
                Plugin::getInstance()->getDiscounts()->deleteDiscountById($discountId);
            }
        }
    }

    /**
     * @return array[]
     */
    public function gatAllActiveDiscountsDataProvider(): array
    {
        $yesterday = (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(12, 0)->modify('-1 day');
        $tomorrow = (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->setTime(12, 0)->modify('+1 day');

        function _createDiscounts($discounts)
        {
            return collect($discounts)->mapWithKeys(function(array $d, string $key) {
                return [$key => array_merge([
                    'name' => 'Discount - ' . $key,
                    'perItemDiscount' => '1',
                    'enabled' => true,
                    'allCategories' => true,
                    'allPurchasables' => true,
                    'percentageOffSubject' => 'original',
                ], $d)];
            })->all();
        }

        return [
            'no-order' => [false, 1, []],
            'order-with-valid-coupon' => [['couponCode' => 'discount_1'], 1, []],
            'order-with-invalid-coupon' => [['couponCode' => 'coupon_code_doesnt_exist'], 0, []],
            'order-discounts-dates' => [
                [],
                3,
                _createDiscounts([
                    'date-from-valid' => [
                        'dateFrom' => $yesterday,
                    ],
                    'date-from-invalid' => [
                        'dateFrom' => $tomorrow,
                    ],
                    'date-to-valid' => [
                        'dateTo' => $tomorrow,
                    ],
                    'date-to-invalid' => [
                        'dateTo' => $yesterday,
                    ],
                    'date-to-from-valid' => [
                        'dateFrom' => $yesterday,
                        'dateTo' => $tomorrow,
                    ],
                    'date-to-from-invalid' => [
                        'dateFrom' => $tomorrow,
                        'dateTo' => $tomorrow->modify('+1 day'),
                    ],
                ]),
            ],
            'order-discounts-limits' => [
                [],
                4,
                _createDiscounts([
                    'total-limit-zero' => [
                        'totalDiscountUseLimit' => 0,
                    ],
                    'total-limit-zero-with-uses' => [
                        'totalDiscountUses' => 10,
                        'totalDiscountUseLimit' => 0,
                    ],
                    'total-limit-valid-with-no-uses' => [
                        'totalDiscountUses' => 0,
                        'totalDiscountUseLimit' => 10,
                    ],
                    'total-limit-valid-with-uses' => [
                        'totalDiscountUses' => 7,
                        'totalDiscountUseLimit' => 10,
                    ],
                    'total-limit-invalid-equals' => [
                        'totalDiscountUses' => 10,
                        'totalDiscountUseLimit' => 10,
                    ],
                    'total-limit-invalid-extra' => [
                        'totalDiscountUses' => 11,
                        'totalDiscountUseLimit' => 10,
                    ],
                ]),
            ],
            'order-discounts-email-limits-no-email' => [
                [],
                1,
                _createDiscounts([
                    'total-limit-zero' => [
                        'perEmailLimit' => 0,
                    ],
                    'total-limit' => [
                        'perEmailLimit' => 1,
                    ],
                ]),
            ],
            'order-discounts-email-limits' => [
                ['email' => 'per.email.limit@crafttest.com'],
                4,
                _createDiscounts([
                    'total-limit-zero' => [
                        'perEmailLimit' => 0,
                    ],
                    'total-limit-zero-with-uses' => [
                        '_emailUses' => ['per.email.limit@crafttest.com' => 10],
                        'perEmailLimit' => 0,
                    ],
                    'total-limit-valid-with-no-uses' => [
                        'perEmailLimit' => 10,
                    ],
                    'total-limit-valid-with-uses' => [
                        '_emailUses' => ['per.email.limit@crafttest.com' => 7],
                        'perEmailLimit' => 10,
                    ],
                    'total-limit-invalid-equals' => [
                        '_emailUses' => ['per.email.limit@crafttest.com' => 10],
                        'perEmailLimit' => 10,
                    ],
                    'total-limit-invalid-extra' => [
                        '_emailUses' => ['per.email.limit@crafttest.com' => 11],
                        'perEmailLimit' => 10,
                    ],
                ]),
            ],
            'purchase-total-limit-no-items' => [
                [],
                4,
                _createDiscounts([
                    'purchase-total-zero' => [
                        'purchaseTotal' => 0,
                    ],
                    'purchase-total-all-purchasables-false' => [
                        'purchaseTotal' => 10,
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                    ],
                    'purchase-total-all-categories-false' => [
                        'purchaseTotal' => 10,
                        'allCategories' => false,
                        'categoryIds' => ['commerce-category'],
                    ],
                    'purchase-total-both-all-false' => [
                        'purchaseTotal' => 10,
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                        'allCategories' => false,
                        'categoryIds' => ['commerce-category'],
                    ],
                ]),
            ],
            'purchase-total-limit-with-items' => [
                [
                    '_lineItems' => ['rad-hood' => 1],
                ],
                5,
                _createDiscounts([
                    'purchase-total-zero' => [
                        'purchaseTotal' => 0,
                    ],
                    'purchase-total-all-purchasables-false' => [
                        'purchaseTotal' => 10,
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                    ],
                    'purchase-total-all-categories-false' => [
                        'purchaseTotal' => 10,
                        'allCategories' => false,
                        'categoryIds' => ['commerce-category'],
                    ],
                    'purchase-total-both-all-false' => [
                        'purchaseTotal' => 10,
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                        'allCategories' => false,
                        'categoryIds' => ['commerce-category'],
                    ],
                    'purchase-total-valid' => [
                        'purchaseTotal' => 150,
                    ],
                    'purchase-total-invalid' => [
                        'purchaseTotal' => 10.99,
                    ],
                ]),
            ],
            'qty-limits-no-items' => [
                [],
                6,
                _createDiscounts([
                    'purchase-qty-zero' => [
                        'purchaseQty' => 0,
                    ],
                    'max-qty-zero' => [
                        'maxPurchaseQty' => 0,
                    ],
                    'both-zero' => [
                        'purchaseQty' => 0,
                        'maxPurchaseQty' => 0,
                    ],
                    'purchase-qty-all-purchasables-false' => [
                        'purchaseQty' => 4,
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                    ],
                    'purchase-total-all-categories-false' => [
                        'purchaseTotal' => 10,
                        'allCategories' => false,
                        'categoryIds' => ['commerce-category'],
                    ],
                    'purchase-total-both-all-false' => [
                        'purchaseTotal' => 10,
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                        'allCategories' => false,
                        'categoryIds' => ['commerce-category'],
                    ],
                ]),
            ],
            'qty-limits-with-items' => [
                ['_lineItems' => ['rad-hood' => 4]],
                6,
                _createDiscounts([
                    'purchase-qty-zero' => [
                        'purchaseQty' => 0,
                    ],
                    'max-qty-zero' => [
                        'maxPurchaseQty' => 0,
                    ],
                    'both-zero' => [
                        'purchaseQty' => 0,
                        'maxPurchaseQty' => 0,
                    ],
                    'purchase-qty-valid' => [
                        'purchaseQty' => 3,
                    ],
                    'purchase-qty-invalid' => [
                        'purchaseQty' => 5,
                    ],
                    'max-qty-valid' => [
                        'maxPurchaseQty' => 10,
                    ],
                    'max-qty-invalid' => [
                        'maxPurchaseQty' => 3,
                    ],
                    'both-valid' => [
                        'purchaseQty' => 2,
                        'maxPurchaseQty' => 10,
                    ],
                    'both-invalid' => [
                        'purchaseQty' => 10,
                        'maxPurchaseQty' => 14,
                    ],
                ]),
            ],
            'purchasables-one-lineitem' => [
                ['_lineItems' => ['rad-hood' => 1]],
                3,
                _createDiscounts([
                    'all-purchasables' => [
                        'allPurchasables' => true,
                    ],
                    'one-to-one' => [
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                    ],
                    'one-to-many' => [
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood', 'hct-white'],
                    ],
                    'no-match' => [
                        'allPurchasables' => false,
                        'purchasableIds' => ['hct-blue'],
                    ],
                ]),
            ],
            'purchasables-multi-lineitems' => [
                ['_lineItems' => ['rad-hood' => 1, 'hct-white' => 1]],
                2,
                _createDiscounts([
                    'one' => [
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood'],
                    ],
                    'many' => [
                        'allPurchasables' => false,
                        'purchasableIds' => ['rad-hood', 'hct-white'],
                    ],
                    'no-match' => [
                        'allPurchasables' => false,
                        'purchasableIds' => ['hct-blue'],
                    ],
                ]),
            ],
        ];
    }

    /**
     * @param array $orderConfig
     * @param array $lineItemConfig
     * @param array $discountConfig
     * @param array $purchasableConfig
     * @param bool $desiredResult
     * @throws \Exception
     */
    protected function matchLineItems(array $orderConfig, array $lineItemConfig, array $discountConfig, array $purchasableConfig, bool $desiredResult)
    {
        $order = new Order($orderConfig);
        $lineItem = new LineItem($lineItemConfig);
        $lineItem->setOrder($order);

        $lineItem->setPurchasable(
            new Purchasable($purchasableConfig)
        );

        $discount = new Discount($discountConfig);

        $this->assertSame(
            $desiredResult,
            $this->discounts->matchLineItem($lineItem, $discount)
        );
    }

    /**
     * @param int $discountId
     *
     * @return Discount
     */
    protected function getDiscountById(int $discountId): Discount
    {
        return Plugin::getInstance()->discounts->getDiscountById($discountId);
    }

    /**
     * @param array $data
     * @throws Exception
     */
    protected function updateOrderCoupon(array $data)
    {
        $discount = $this->tester->grabFixture('discounts')['discount_with_coupon'];
        Craft::$app->getDb()->createCommand()
            ->update(
                Table::DISCOUNTS,
                $data,
                ['id' => $discount['id']]
            )->execute();
    }

    /**
     * @param array $orderConfig
     * @param bool $desiredResult
     * @param string $desiredExplanation
     */
    protected function orderCouponAvailableTest(array $orderConfig, bool $desiredResult, string $desiredExplanation = '')
    {
        $order = new Order($orderConfig);

        $explanation = '';
        $result = $this->discounts->orderCouponAvailable($order, $explanation);
        self::assertSame($desiredResult, $result);
        self::assertSame($desiredExplanation, $explanation);
    }

    /**
     *
     */
    protected function _before()
    {
        parent::_before();
        $this->discounts = Plugin::getInstance()->getDiscounts();
        $customerFixture = $this->tester->grabFixture('customers');
        $this->_user = $customerFixture->getElement('customer1');
        Craft::$app->getUser()->setIdentity($this->_user);
    }

    protected function _after()
    {
        Craft::$app->getUser()->setIdentity(null);
        parent::_after();
    }
}
