<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static array|null getAllCodes()
 * @method static \CraftCms\Commerce\Promotion\Data\Coupon|null getCouponByCode(string $code)
 * @method static \CraftCms\Commerce\Promotion\Data\Coupon[] getCouponsByDiscountId(int $discountId)
 * @method static string[] generateCouponCodes(int $count = 1, string $format = '######', string[] $existingCodes = [])
 * @method static bool deleteCouponById(int $id)
 * @method static bool saveDiscountCoupons(\CraftCms\Commerce\Promotion\Data\Discount $discount)
 * @method static bool saveCoupon(\CraftCms\Commerce\Promotion\Data\Coupon $coupon, bool $runValidation = true)
 *
 * @see \CraftCms\Commerce\Promotion\Coupons
 */
class Coupons extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Promotion\Coupons::class;
    }
}
