<?php

namespace craft\commerce\services;

use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Discounts::class)` instead.
 */
class Discounts extends Component
{
    public function getDiscountById(int $id, ?int $storeId = null): ?Discount
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->getDiscountById($id, $storeId);
    }

    /**
     * @return Collection<int, Discount>
     */
    public function getAllDiscounts(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->getAllDiscounts($storeId);
    }

    /**
     * @return Discount[]
     */
    public function getAllActiveDiscounts(?Order $order = null): array
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->getAllActiveDiscounts($order);
    }

    public function orderCouponAvailable(Order $order, ?string &$explanation = null): bool
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->orderCouponAvailable($order, $explanation);
    }

    public function getDiscountByCode(?string $code, ?int $storeId = null): ?Discount
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->getDiscountByCode($code, $storeId);
    }

    /**
     * @return Discount[]
     */
    public function getDiscountsRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->getDiscountsRelatedToPurchasable($purchasable);
    }

    public function matchLineItem(LineItem $lineItem, Discount $discount, bool $matchOrder = false): bool
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->matchLineItem($lineItem, $discount, $matchOrder);
    }

    public function matchOrder(Order $order, Discount $discount): bool
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->matchOrder($order, $discount);
    }

    public function saveDiscount(Discount $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->saveDiscount($model, $runValidation);
    }

    public function deleteDiscountById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->deleteDiscountById($id);
    }

    public function ensureSortOrder(?int $storeId = null): void
    {
        app(\CraftCms\Commerce\Services\Discounts::class)->ensureSortOrder($storeId);
    }

    public function clearCustomerUsageHistoryById(int $id): void
    {
        app(\CraftCms\Commerce\Services\Discounts::class)->clearCustomerUsageHistoryById($id);
    }

    public function clearEmailUsageHistoryById(int $id): void
    {
        app(\CraftCms\Commerce\Services\Discounts::class)->clearEmailUsageHistoryById($id);
    }

    public function clearDiscountUsesById(int $id): void
    {
        app(\CraftCms\Commerce\Services\Discounts::class)->clearDiscountUsesById($id);
    }

    public function reorderDiscounts(array $ids): bool
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->reorderDiscounts($ids);
    }

    public function appendCouponCode(int $discountId, string|Coupon $coupon, ?int $maxUses = null): bool
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->appendCouponCode($discountId, $coupon, $maxUses);
    }

    public function getEmailUsageStatsById(int $id): array
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->getEmailUsageStatsById($id);
    }

    public function getCustomerUsageStatsById(int $id): array
    {
        return app(\CraftCms\Commerce\Services\Discounts::class)->getCustomerUsageStatsById($id);
    }

    public function orderCompleteHandler(Order $order): void
    {
        app(\CraftCms\Commerce\Services\Discounts::class)->orderCompleteHandler($order);
    }
}
