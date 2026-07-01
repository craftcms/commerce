<?php

namespace craft\commerce\services;

use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Coupons::class)` instead.
 */
class Coupons extends Component
{
    public const COUPON_FORMAT_REPLACEMENT_CHAR = \CraftCms\Commerce\Services\Coupons::COUPON_FORMAT_REPLACEMENT_CHAR;
    public const DEFAULT_COUPON_FORMAT = \CraftCms\Commerce\Services\Coupons::DEFAULT_COUPON_FORMAT;
    public const CHARS_UPPER = \CraftCms\Commerce\Services\Coupons::CHARS_UPPER;
    public const CHARS_LOWER = \CraftCms\Commerce\Services\Coupons::CHARS_LOWER;
    public const CHARS_NUMBERS = \CraftCms\Commerce\Services\Coupons::CHARS_NUMBERS;
    public const CHARS_SPECIAL = \CraftCms\Commerce\Services\Coupons::CHARS_SPECIAL;

    public function getAllCodes(): ?array
    {
        return app(\CraftCms\Commerce\Services\Coupons::class)->getAllCodes();
    }

    public function getCouponByCode(string $code): ?Coupon
    {
        return app(\CraftCms\Commerce\Services\Coupons::class)->getCouponByCode($code);
    }

    /**
     * @return Coupon[]
     */
    public function getCouponsByDiscountId(int $discountId): array
    {
        return app(\CraftCms\Commerce\Services\Coupons::class)->getCouponsByDiscountId($discountId);
    }

    /**
     * @param string[] $existingCodes
     * @return string[]
     * @throws \Exception
     */
    public function generateCouponCodes(int $count = 1, string $format = self::DEFAULT_COUPON_FORMAT, array $existingCodes = []): array
    {
        return app(\CraftCms\Commerce\Services\Coupons::class)->generateCouponCodes($count, $format, $existingCodes);
    }

    public function deleteCouponById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\Coupons::class)->deleteCouponById($id);
    }

    public function saveDiscountCoupons(Discount $discount): bool
    {
        return app(\CraftCms\Commerce\Services\Coupons::class)->saveDiscountCoupons($discount);
    }

    public function saveCoupon(Coupon $coupon, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\Coupons::class)->saveCoupon($coupon, $runValidation);
    }
}
