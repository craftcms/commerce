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
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\services\Discounts;
use craft\commerce\test\mockclasses\Purchasable;
use craft\db\Query;
use craft\elements\User;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\DiscountsFixture;
use DateInterval;
use DateTime;
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
                'class' => DiscountsFixture::class
            ],
            'customers' => [
                'class' => CustomerFixture::class
            ]
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
            'dateTo' => '2019-05-01 10:21:33'
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
            'dateFrom' => $date->format('Y-m-d H:i:s')
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
            'totalDiscountUses' => 2
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
            'perUserLimit' => true
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
            'perUserLimit' => '1'
        ]);

        Craft::$app->getDb()->createCommand()
            ->insert(Table::USER_DISCOUNTUSES, [
                'userId' => $this->_user->id,
                'discountId' => '1000',
                'uses' => '1',
            ])->execute();

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => $this->_user->id],
            false,
            'This coupon is for registered users and limited to 1 uses.'
        );

        Craft::$app->getDb()->createCommand()->truncateTable(TABLE::USER_DISCOUNTUSES)->execute();
    }

    /**
     * @throws Exception
     * @todo Replace stub with fixture data. #COM-54
     *
     */
    public function testCouponPerEmailLimit(): void
    {
        $this->updateOrderCoupon([
            'perEmailLimit' => '1'
        ]);

        Craft::$app->getDb()->createCommand()
            ->insert(Table::EMAIL_DISCOUNTUSES, [
                'email' => 'testing@craftcommerce.com',
                'discountId' => '1000',
                'uses' => '1'
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

        Craft::$app->getDb()->createCommand()->truncateTable(TABLE::USER_DISCOUNTUSES)->execute();
    }

    /**
     *
     */
    public function testLineItemMatchingSuccess(): void
    {
        $this->matchLineItems(
            ['couponCode' => null],
            ['qty' => 2, 'salePrice' => 10],
            ['code' => null],
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
            ['code' => null, 'excludeOnSale' => true],
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
            ['code' => null],
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
        // TODO: Update this test to create a full real order that saves. #COM-54

        /** @var Order $order */
        $order = $this->make(Order::class, [
            'getAdjustmentsByType' => function($type) {
                $adjustment = new OrderAdjustment();
                $adjustment->sourceSnapshot = ['discountUseId' => 1000];

                return [$adjustment];
            }
        ]);
        $order->couponCode = 'discount_1';
        $order->customerId = $this->_user->id;

        $this->updateOrderCoupon([
            'perUserLimit' => '0',
            'perEmailLimit' => '0'
        ]);

        $this->discounts->orderCompleteHandler($order);

        // Get thew new Total uses.
        $totalUses = (int)(new Query())
            ->select('totalDiscountUses')
            ->from(Table::DISCOUNTS)
            ->where(['code' => 'discount_1'])
            ->scalar();

        self::assertSame(1, $totalUses);

        // Get the Customer Discount Uses
        $customerUses = (new Query())
            ->select('*')
            ->from(Table::USER_DISCOUNTUSES)
            ->where(['userId' => $this->_user->id, 'discountId' => '1000', 'uses' => '1'])
            ->one();

        self::assertNotNull($customerUses);


        // Get the Email Discount Uses
        $customerEmail = $order->getCustomer()->email;
        $customerUses = (new Query())
            ->select('*')
            ->from(Table::EMAIL_DISCOUNTUSES)
            ->where(['email' => $customerEmail, 'discountId' => '1000', 'uses' => '1'])
            ->one();

        self::assertNotNull($customerUses);
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
        Craft::$app->getDb()->createCommand()
            ->update(
                Table::DISCOUNTS,
                $data,
                ['code' => 'discount_1']
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
        $customerFixture =  $this->tester->grabFixture('customers');
        $this->_user = $customerFixture->getElement('customer1');
    }
}
