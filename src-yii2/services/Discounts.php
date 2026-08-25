<?php

namespace craft\commerce\services;

use craft\commerce\base\PurchasableInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Promotion\Models\Coupon;
use CraftCms\Commerce\Promotion\Models\Discount;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Promotion\Discounts::class)` instead.
 */
class Discounts extends Component
{
    public function getDiscountById(int $id, ?int $storeId = null): ?Discount
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->getDiscountById($id, $storeId);
    }

    /**
     * @return Collection<int, Discount>
     */
    public function getAllDiscounts(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->getAllDiscounts($storeId);
    }

    /**
     * @return Discount[]
     */
    public function getAllActiveDiscounts(?Order $order = null): array
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->getAllActiveDiscounts($order);
    }

    public function orderCouponAvailable(Order $order, ?string &$explanation = null): bool
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->orderCouponAvailable($order, $explanation);
    }

    public function getDiscountByCode(?string $code, ?int $storeId = null): ?Discount
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->getDiscountByCode($code, $storeId);
    }

    /**
     * @return Discount[]
     */
    public function getDiscountsRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->getDiscountsRelatedToPurchasable($purchasable);
    }

    public function matchLineItem(LineItem $lineItem, Discount $discount, bool $matchOrder = false): bool
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->matchLineItem($lineItem, $discount, $matchOrder);
    }

    public function matchOrder(Order $order, Discount $discount): bool
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->matchOrder($order, $discount);
    }

    public function saveDiscount(Discount $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->saveDiscount($model, $runValidation);
    }

    public function deleteDiscountById(int $id): bool
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->deleteDiscountById($id);
    }

    public function ensureSortOrder(?int $storeId = null): void
    {
        app(\CraftCms\Commerce\Promotion\Discounts::class)->ensureSortOrder($storeId);
    }

    public function clearCustomerUsageHistoryById(int $id): void
    {
        app(\CraftCms\Commerce\Promotion\Discounts::class)->clearCustomerUsageHistoryById($id);
    }

    public function clearEmailUsageHistoryById(int $id): void
    {
        app(\CraftCms\Commerce\Promotion\Discounts::class)->clearEmailUsageHistoryById($id);
    }

    public function clearDiscountUsesById(int $id): void
    {
        app(\CraftCms\Commerce\Promotion\Discounts::class)->clearDiscountUsesById($id);
    }

    public function reorderDiscounts(array $ids): bool
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->reorderDiscounts($ids);
    }

    public function appendCouponCode(int $discountId, string|Coupon $coupon, ?int $maxUses = null): bool
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->appendCouponCode($discountId, $coupon, $maxUses);
    }

    public function getEmailUsageStatsById(int $id): array
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->getEmailUsageStatsById($id);
    }

    public function getCustomerUsageStatsById(int $id): array
    {
        return app(\CraftCms\Commerce\Promotion\Discounts::class)->getCustomerUsageStatsById($id);
    }

    public function orderCompleteHandler(Order $order): void
    {
        app(\CraftCms\Commerce\Promotion\Discounts::class)->orderCompleteHandler($order);
    }
}
