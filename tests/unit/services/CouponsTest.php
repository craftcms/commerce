<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use craft\commerce\Plugin;
use craft\commerce\services\Coupons;
use craft\helpers\ArrayHelper;
use craftcommercetests\fixtures\DiscountsFixture;
use UnitTester;

/**
 * CouponsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class CouponsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Coupons
     */
    private Coupons $_service;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'discounts' => [
                'class' => DiscountsFixture::class,
            ],
        ];
    }

    /**
     * @return void
     * @throws \Codeception\Exception\ModuleException
     */
    public function testGetAllCodes(): void
    {
        $coupons = $this->tester->grabFixture('discounts')['discount_with_coupon']['coupons'];
        $codes = $this->_service->getAllCodes();

        self::assertIsArray($codes);
        self::assertNotEmpty($codes);
        self::assertContains($coupons[0]->code, $codes);
    }

    /**
     * @dataProvider getCouponByCodeDataProvider
     * @param string $code
     * @param Coupon|null $expectedCoupon
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function testGetCouponByCode(string $code, ?Coupon $expectedCoupon): void
    {
        $coupon = $this->_service->getCouponByCode($code);

        if (!$expectedCoupon) {
            self::assertNull($coupon);
        } else {
            self::assertNotNull($coupon);
            self::assertInstanceOf($expectedCoupon::class, $coupon);
            self::assertEquals($expectedCoupon->code, $coupon->code);
        }
    }

    /**
     * @return \string[][]
     */
    public function getCouponByCodeDataProvider(): array
    {
        return [
            ['discount_1', new Coupon(['code' => 'discount_1'])],
            ['invalid_code', null],
        ];
    }

    /**
     * @return void
     * @throws \Codeception\Exception\ModuleException
     * @throws \yii\base\InvalidConfigException
     */
    public function testGetCouponsByDiscountId(): void
    {
        $discount = $this->tester->grabFixture('discounts')['discount_with_coupon'];

        $coupons = $this->_service->getCouponsByDiscountId(0);

        self::assertIsArray($coupons);
        self::assertEmpty($coupons);

        $coupons = $this->_service->getCouponsByDiscountId($discount['id']);

        self::assertIsArray($coupons);
        self::assertNotEmpty($coupons);
        self::assertContains($discount['coupons'][0]->code, ArrayHelper::getColumn($coupons, 'code'));
    }

    /**
     * @dataProvider generateCouponCodesDataProvider
     * @param int $count
     * @param string $format
     * @param array $existingCodes
     * @param bool $exception
     * @return void
     * @throws \Exception
     */
    public function testGenerateCouponCodes(int $count, string $format, array $existingCodes, bool $exception): void
    {
        if ($exception) {
            $this->expectException(\Exception::class);
        }
        $codes = $this->_service->generateCouponCodes($count, $format, $existingCodes);

        self::assertIsArray($codes);
        self::assertCount($count, $codes);
        if (!empty($existingCodes)) {
            self::assertNotContains($existingCodes, $codes);
        }
        self::assertMatchesRegularExpression('/' . str_replace(Coupons::COUPON_FORMAT_REPLACEMENT_CHAR, '.', $format) . '/', $codes[0]);
    }


    public function generateCouponCodesDataProvider(): array
    {
        return [
            [
                10,
                'commerce_####',
                [],
                false,
            ],
            [
                45,
                'commerce_#',
                [],
                true,
            ],
            [
                25,
                'commerce_#_coupons',
                ['commerce_A_coupons'],
                false,
            ],
        ];
    }

    /**
     * @dataProvider saveCouponDataProvider
     *
     * @param Coupon $newCoupon
     * @param bool $runValidation
     * @param bool $expectedResult
     * @return void
     * @throws \Exception
     */
    public function testSaveCoupon(Coupon $newCoupon, bool $runValidation, bool $expectedResult): void
    {
        $newCoupon->discountId = $this->tester->grabFixture('discounts')['discount_with_coupon']['id'];
        $result = $this->_service->saveCoupon($newCoupon, $runValidation);

        self::assertSame($expectedResult, $result);

        if ($expectedResult) {
            self::assertNotNull($newCoupon->id);
        } else {
            self::assertNull($newCoupon->id);
        }
    }

    /**
     * @return array[]
     */
    public function saveCouponDataProvider(): array
    {
        return [
            [new Coupon(['code' => 'test_code']), true, true],
            [new Coupon(['code' => 'discount_1']), true, false],
        ];
    }

    /**
     * @return void
     * @throws \Codeception\Exception\ModuleException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function testDeleteCouponById(): void
    {
        $couponRecord = New \craft\commerce\records\Coupon();
        $couponRecord->code = 'commerce_test_code';
        $couponRecord->discountId = $this->tester->grabFixture('discounts')['discount_with_coupon']['id'];
        $couponRecord->uses = 0;
        $couponRecord->maxUses = null;
        $couponRecord->save();

        $result = $this->_service->deleteCouponById($couponRecord->id);
        self::assertEquals(true, $result);
    }

    public function testSaveDiscountCoupons(): void
    {
        $discountFixture = $this->tester->grabFixture('discounts')['discount_with_coupon'];
        $newCoupon = new Coupon([
            'id' => $discountFixture['id'],
            'code' => 'new_commerce_coupon',
            'uses' => 0,
        ]);

        $discount = Plugin::getInstance()->getDiscounts()->getDiscountById($discountFixture['id']);
        $discount->setCoupons([...$discount->getCoupons(), $newCoupon]);

        $result = $this->_service->saveDiscountCoupons($discount);
        self::assertEquals(true, $result);

        $discount->setCoupons([]);

        $result = $this->_service->saveDiscountCoupons($discount);
        self::assertEquals(true, $result);

        $this->expectException(\Exception::class);
        $this->_service->saveDiscountCoupons(new Discount());
    }

    /**
     *
     */
    protected function _before(): void
    {
        parent::_before();

        $this->_service = Plugin::getInstance()->getCoupons();
    }
}