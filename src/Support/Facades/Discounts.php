<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Promotion\Data\Discount|null getDiscountById(int $id, int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllDiscounts(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Promotion\Data\Discount[] getAllActiveDiscounts(\CraftCms\Commerce\Order\Elements\Order|null $order = null)
 * @method static bool orderCouponAvailable(\CraftCms\Commerce\Order\Elements\Order $order, string|null $explanation = null)
 * @method static \CraftCms\Commerce\Promotion\Data\Discount|null getDiscountByCode(string|null $code, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Promotion\Data\Discount[] getDiscountsRelatedToPurchasable(\CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface $purchasable)
 * @method static bool matchLineItem(\CraftCms\Commerce\Order\LineItem\Data\LineItem $lineItem, \CraftCms\Commerce\Promotion\Data\Discount $discount, bool $matchOrder = false)
 * @method static bool matchOrder(\CraftCms\Commerce\Order\Elements\Order $order, \CraftCms\Commerce\Promotion\Data\Discount $discount)
 * @method static bool saveDiscount(\CraftCms\Commerce\Promotion\Data\Discount $model, bool $runValidation = true)
 * @method static bool deleteDiscountById(int $id)
 * @method static void ensureSortOrder(int|null $storeId = null)
 * @method static void clearCustomerUsageHistoryById(int $id)
 * @method static void clearEmailUsageHistoryById(int $id)
 * @method static void clearDiscountUsesById(int $id)
 * @method static bool reorderDiscounts(array $ids)
 * @method static bool moveDiscountToPosition(int $id, int $toPosition)
 * @method static bool appendCouponCode(int $discountId, \CraftCms\Commerce\Promotion\Data\Coupon|string $coupon, int|null $maxUses = null)
 * @method static array getEmailUsageStatsById(int $id)
 * @method static array getCustomerUsageStatsById(int $id)
 * @method static void orderCompleteHandler(\CraftCms\Commerce\Order\Elements\Order $order)
 *
 * @see \CraftCms\Commerce\Promotion\Discounts
 */
class Discounts extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Promotion\Discounts::class;
    }
}
