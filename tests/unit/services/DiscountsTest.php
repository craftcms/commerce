<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommerce\tests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\services\Discounts;
use craft\commerce\test\mockclasses\Purchasable;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craftcommerce\tests\fixtures\CustomerFixture;
use craftcommerce\tests\fixtures\DiscountsFixture;
use UnitTester;
use DateTime;
use DateInterval;

/**
 * DiscountsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class DiscountsTest extends Unit
{
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Discounts $discounts
     */
    protected $discounts;

    // Public methods
    // =========================================================================

    /**
     * @return array
     */
    public function _fixtures() : array
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

    // Tests
    // =========================================================================

    /**
     *
     */
    public function testOrderCouponAvailableWithInvalidCoupon()
    {
        $this->orderCouponAvailableTest(
            ['couponCode' => 'invalid_coupon'],
            false,
            'Coupon not valid'
        );
    }

    /**
     *
     */
    public function testSuccessOrderCouponAvailable()
    {
        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1000'],
            true,
            ''
        );
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testExistingCouponNotEnabled()
    {
        // Set it to be disabled
        $this->updateOrderCoupon([
            'enabled' => false,
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1000'],
            false,
            'Coupon not valid'
        );
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testOrderCouponExpired()
    {
        // Invalidate the coupon.... It's valid untill sometime in the past.
        $this->updateOrderCoupon([
            'dateTo' => '2019-05-01 10:21:33'
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1000'],
            false,
            'Discount is out of date'
        );
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testOrderCouponNotYetValid()
    {
        // Set the coupon to start in two days from now.
        $date = new DateTime('now');
        $date->add(new DateInterval('P2D'));
        $this->updateOrderCoupon([
            'dateFrom' => $date->format('Y-m-d H:i:s')
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1000'],
            false,
            'Discount is out of date'
        );
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testCouponThatHasBeenUsedTooMuch()
    {
        $this->updateOrderCoupon([
            'totalUses' => '2'
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1000'],
            false,
            'Discount use has reached its limit'
        );
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testCouponWithUseLimitAndNoUserOnClient()
    {
        $this->updateOrderCoupon([
            'perUserLimit' => '1'
        ]);

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1001'],
            false,
            'Discount is limited to use by registered users only.'
        );
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testCouponPerUserLimit()
    {
        $this->updateOrderCoupon([
            'perUserLimit' => '1'
        ]);

        \Craft::$app->getDb()->createCommand()
            ->insert('{{%commerce_customer_discountuses}}', [
                'customerId' => '1000',
                'discountId' => '1000',
                'uses' => '1',
            ])->execute();

        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1000'],
            false,
            'This coupon limited to 1 uses.'
        );
    }

    /**
     * @todo Replace stub with fixture data.
     * @throws \yii\db\Exception
     */
    public function testCouponPerEmailLimit()
    {
        $this->updateOrderCoupon([
            'perEmailLimit' => '1'
        ]);

        \Craft::$app->getDb()->createCommand()
            ->insert('{{%commerce_email_discountuses}}', [
                'email' => 'testing@craftcommerce.com',
                'discountId' => '1000',
                'uses' => '1'
            ])->execute();


        $order = Stub::construct(
            Order::class,
            [['couponCode' => 'discount_1', 'customerId' => '1000']],
            ['getEmail' => 'testing@craftcommerce.com']
        );

        $explanation = '';
        $result = $this->discounts->orderCouponAvailable($order, $explanation);
        $this->assertFalse($result);
        $this->assertSame('This coupon limited to 1 uses.', $explanation);
    }

    /**
     *
     */
    public function testLineItemMatchingSuccess()
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
    public function testLineItemMatchingSaleFail()
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
    public function testLineItemMatchingIfNotPromotable()
    {
        $this->matchLineItems(
            ['couponCode' => null],
            ['qty' => 2, 'price' => 15],
            ['code' => null],
            ['isPromotable' => false],
            false
        );
    }

    // @todo: Test the lineItemMatching category and purchasableIds based features. Aswell as see coverage to see
    // @todo: if everything is covered.


    /**
     * @throws \yii\db\Exception
     */
    public function testOrderCompleteHandler()
    {
        $this->updateOrderCoupon([
            'totalUseLimit' => '2',
            'totalUses' => '2',
            'perUserLimit' => '1',
            'perEmailLimit' => '1'
        ]);

        $order = new Order(['couponCode' => 'discount_1', 'customerId' => '1000']);

        $this->discounts->orderCompleteHandler($order);

        // Get thew new Total uses.
        $totalUses = (new Query())
            ->select('totalUses')
            ->from('{{%commerce_discounts}}')
            ->where(['code' => 'discount_1'])
            ->scalar();
        $this->assertSame(3, $totalUses);

        // Get the Customer Discount Uses
        $customerUses = (new Query())
            ->select('*')
            ->from('{{%commerce_customer_discountuses}}')
            ->where(['customerId' => '1000', 'discountId' => '1000', 'uses' => '1'])
            ->one();
        $this->assertNotNull($customerUses);


        // Get the Email Discount Uses
        $customerEmail = $order->getCustomer()->getUser()->email;
        $customerUses = (new Query())
            ->select('*')
            ->from('{{%commerce_email_discountuses}}')
            ->where(['email' => $customerEmail, 'discountId' => '1000', 'uses' => '1'])
            ->one();
        $this->assertNotNull($customerUses);
    }

    /**
     *
     */
    public function testVoidIfNoCouponCode()
    {
        $order = new Order(['couponCode' => null]);
        $this->assertNull(
            $this->discounts->orderCompleteHandler($order)
        );
    }

    /**
     *
     */
    public function testVoidIfInvalidCouponCode()
    {
        $order = new Order(['couponCode' => 'i_dont_exist_as_coupon']);
        $this->assertNull(
            $this->discounts->orderCompleteHandler($order)
        );
    }

    // Protected methods
    // =========================================================================

    protected function matchLineItems(
        array $orderConfig,
        array $lineItemConfig,
        array $discountConfig,
        array $purchasableConfig,
        bool $desiredResult
    ) {
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
     * @return Discount
     */
    protected function getDiscountById(int $discountId) : Discount
    {
        return Plugin::getInstance()->discounts->getDiscountById($discountId);
    }

    /**
     * @param array $data
     * @throws \yii\db\Exception
     */
    protected function updateOrderCoupon(array $data)
    {
        \Craft::$app->getDb()->createCommand()
            ->update(
                '{{%commerce_discounts}}',
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
        $this->assertSame($desiredResult, $result);
        $this->assertSame($desiredExplanation, $explanation);
    }

    /**
     *
     */
    protected function _before()
    {
        parent::_before();

        $this->discounts = Plugin::getInstance()->getDiscounts();
    }
}
