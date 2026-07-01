<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface;
use CraftCms\Commerce\Shipping\Models\ShippingMethod;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\ShippingMethods::class)` instead.
 */
class ShippingMethods extends Component
{
    public const EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS = 'registerAvailableShippingMethods';

    /**
     * @return Collection<int, ShippingMethod>
     */
    public function getAllShippingMethods(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->getAllShippingMethods($storeId);
    }

    public function getShippingMethodByHandle(string $handle, ?int $storeId = null): ?ShippingMethod
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->getShippingMethodByHandle($handle, $storeId);
    }

    public function getShippingMethodById(int $id, ?int $storeId = null): ?ShippingMethod
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->getShippingMethodById($id, $storeId);
    }

    /**
     * @return array<string, ShippingMethod>
     */
    public function getMatchingShippingMethods(Order $order): array
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->getMatchingShippingMethods($order);
    }

    public function getSerializedOrderForMatchingRules(Order $order): array
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->getSerializedOrderForMatchingRules($order);
    }

    public function getMatchingShippingRule(Order $order, ShippingMethodInterface $method): ?ShippingRuleInterface
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->getMatchingShippingRule($order, $method);
    }

    public function saveShippingMethod(ShippingMethod $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->saveShippingMethod($model, $runValidation);
    }

    public function deleteShippingMethodById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\ShippingMethods::class)->deleteShippingMethodById($id);
    }

    public function clearCache(): void
    {
        app(\CraftCms\Commerce\Services\ShippingMethods::class)->clearCache();
    }
}
