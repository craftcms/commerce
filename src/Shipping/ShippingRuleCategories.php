<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\Data\ShippingRuleCategory;
use CraftCms\Commerce\Shipping\Models\ShippingRuleCategory as ShippingRuleCategoryRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;

#[Singleton]
class ShippingRuleCategories
{
    /**
     * @var array<int, array<int, ShippingRuleCategory>>|null
     */
    private ?array $allShippingRuleCategories = null;

    /**
     * Returns all shipping rule categories, keyed by rule ID then category ID, memoized for the
     * lifetime of the request to avoid N+1 queries when categories are fetched one rule at a time.
     */
    public function getAllShippingRuleCategoriesData(): array
    {
        if ($this->allShippingRuleCategories === null) {
            $rows = $this->query()->get();
            $categoriesByRuleId = [];

            foreach ($rows as $row) {
                $row = (array) $row;
                $categoriesByRuleId[$row['shippingRuleId']][$row['shippingCategoryId']] = new ShippingRuleCategory($row);
            }

            $this->allShippingRuleCategories = $categoriesByRuleId;
        }

        return $this->allShippingRuleCategories;
    }

    /**
     * @return array<int, ShippingRuleCategory>
     */
    public function getShippingRuleCategoriesByRuleId(int $ruleId): array
    {
        return $this->getAllShippingRuleCategoriesData()[$ruleId] ?? [];
    }

    /**
     * @param int[] $ruleIds
     * @return array<int, array<int, ShippingRuleCategory>>
     */
    public function getShippingRuleCategoriesByRuleIds(array $ruleIds): array
    {
        if (empty($ruleIds)) {
            return [];
        }

        $rows = $this->query()->whereIn('shippingRuleId', $ruleIds)->get()->all();
        $categoriesByRuleId = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $categoriesByRuleId[$row['shippingRuleId']][$row['shippingCategoryId']] = new ShippingRuleCategory($row);
        }

        return $categoriesByRuleId;
    }

    public function createShippingRuleCategory(ShippingRuleCategory $model, bool $runValidation = true): bool
    {
        if ($runValidation && !$model->validate()) {
            return false;
        }

        $record = new ShippingRuleCategoryRecord();

        $record->shippingRuleId = $model->shippingRuleId;
        $record->shippingCategoryId = $model->shippingCategoryId;
        $record->condition = $model->condition;
        $record->perItemRate = $model->perItemRate;
        $record->weightRate = $model->weightRate;
        $record->percentageRate = $model->percentageRate;

        $record->save();
        $model->id = $record->id;

        $this->allShippingRuleCategories = null;

        return true;
    }

    public function deleteShippingRuleCategoryById(int $id): bool
    {
        $record = ShippingRuleCategoryRecord::find($id);

        if ($record) {
            $this->allShippingRuleCategories = null;

            return (bool) $record->delete();
        }

        return false;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::SHIPPINGRULE_CATEGORIES)
            ->select([
                'condition',
                'id',
                'percentageRate',
                'perItemRate',
                'shippingCategoryId',
                'shippingRuleId',
                'weightRate',
            ]);
    }
}
