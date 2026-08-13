<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Shipping\Models\ShippingRuleCategory;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Shipping\ShippingRuleCategories::class)` instead.
 */
class ShippingRuleCategories extends Component
{
    /**
     * @return array<int, ShippingRuleCategory>
     */
    public function getShippingRuleCategoriesByRuleId(int $ruleId): array
    {
        return app(\CraftCms\Commerce\Shipping\ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleId($ruleId);
    }

    /**
     * @param int[] $ruleIds
     * @return array<int, array<int, ShippingRuleCategory>>
     */
    public function getShippingRuleCategoriesByRuleIds(array $ruleIds): array
    {
        return app(\CraftCms\Commerce\Shipping\ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleIds($ruleIds);
    }

    public function createShippingRuleCategory(ShippingRuleCategory $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Shipping\ShippingRuleCategories::class)->createShippingRuleCategory($model, $runValidation);
    }

    public function deleteShippingRuleCategoryById(int $id): bool
    {
        return app(\CraftCms\Commerce\Shipping\ShippingRuleCategories::class)->deleteShippingRuleCategoryById($id);
    }
}
