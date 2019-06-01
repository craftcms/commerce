<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommerce\tests\unit;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\services\Discounts;
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
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Discounts $discounts
     */
    protected $discounts;

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



    public function testOrderCouponAvailableWithInvalidCoupon()
    {
        $this->orderCouponAvailableTest(
            ['couponCode' => 'invalid_coupon'],
            false,
            'Coupon not valid'
        );
    }

    public function testSuccessOrderCouponAvailable()
    {
        $this->orderCouponAvailableTest(
            ['couponCode' => 'discount_1', 'customerId' => '1000'],
            true,
            ''
        );
    }

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

    protected function updateOrderCoupon(array $data)
    {
        \Craft::$app->getDb()->createCommand()
            ->update(
                '{{%commerce_discounts}}',
                $data,
                ['code' => 'discount_1']
            )->execute();
    }

    protected function orderCouponAvailableTest(array $orderConfig, bool $desiredResult, string $desiredExplanation = '')
    {
        $order = new Order($orderConfig);

        $explanation = '';
        $result = $this->discounts->orderCouponAvailable($order, $explanation);
        $this->assertSame($desiredResult, $result);
        $this->assertSame($desiredExplanation, $explanation);
    }

    protected function _before()
    {
        parent::_before();

        $this->discounts = Plugin::getInstance()->getDiscounts();
    }
}
