<?php

namespace craft\commerce\services;

use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use CraftCms\Commerce\Promotion\Models\Sale;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Promotion\Sales::class)` instead.
 */
class Sales extends Component
{
    public function canUseSales(): bool
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->canUseSales();
    }

    public function getSaleById(int $id): ?Sale
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->getSaleById($id);
    }

    /**
     * @return Sale[]
     */
    public function getAllSales(): array
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->getAllSales();
    }

    /**
     * @return Sale[]
     */
    public function getSalesForPurchasable(PurchasableInterface $purchasable, ?Order $order = null): array
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->getSalesForPurchasable($purchasable, $order);
    }

    /**
     * @return Sale[]
     */
    public function getSalesRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->getSalesRelatedToPurchasable($purchasable);
    }

    public function getSalePriceForPurchasable(PurchasableInterface $purchasable, ?Order $order = null): float
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->getSalePriceForPurchasable($purchasable, $order);
    }

    public function matchPurchasableAndSale(PurchasableInterface $purchasable, Sale $sale, ?Order $order = null): bool
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->matchPurchasableAndSale($purchasable, $sale, $order);
    }

    public function saveSale(Sale $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->saveSale($model, $runValidation);
    }

    public function reorderSales(array $ids): bool
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->reorderSales($ids);
    }

    public function deleteSaleById(int $id): bool
    {
        return app(\CraftCms\Commerce\Promotion\Sales::class)->deleteSaleById($id);
    }
}
