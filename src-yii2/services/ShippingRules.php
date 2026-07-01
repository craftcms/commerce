<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Shipping\Models\ShippingRule;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\ShippingRules::class)` instead.
 */
class ShippingRules extends Component
{
    /**
     * @return Collection<int, ShippingRule>
     */
    public function getAllShippingRules(): Collection
    {
        return app(\CraftCms\Commerce\Services\ShippingRules::class)->getAllShippingRules();
    }

    /**
     * @return Collection<int, ShippingRule>
     */
    public function getAllShippingRulesByShippingMethodId(int $methodId): Collection
    {
        return app(\CraftCms\Commerce\Services\ShippingRules::class)->getAllShippingRulesByShippingMethodId($methodId);
    }

    public function getShippingRuleById(int $id): ?ShippingRule
    {
        return app(\CraftCms\Commerce\Services\ShippingRules::class)->getShippingRuleById($id);
    }

    public function saveShippingRule(ShippingRule $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\ShippingRules::class)->saveShippingRule($model, $runValidation);
    }

    public function reorderShippingRules(array $ids): bool
    {
        return app(\CraftCms\Commerce\Services\ShippingRules::class)->reorderShippingRules($ids);
    }

    public function deleteShippingRuleById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\ShippingRules::class)->deleteShippingRuleById($id);
    }
}
