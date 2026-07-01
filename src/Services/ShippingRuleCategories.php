<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\Models\ShippingRuleCategory;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;

#[Singleton]
class ShippingRuleCategories
{
    /**
     * @return array<int, ShippingRuleCategory>
     */
    public function getShippingRuleCategoriesByRuleId(int $ruleId): array
    {
        $rows = $this->query()->where('shippingRuleId', $ruleId)->get()->all();
        $categories = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $categories[$row['shippingCategoryId']] = new ShippingRuleCategory($row);
        }

        return $categories;
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

        /** @phpstan-ignore-next-line */
        $record->shippingRuleId = $model->shippingRuleId;
        /** @phpstan-ignore-next-line */
        $record->shippingCategoryId = $model->shippingCategoryId;
        /** @phpstan-ignore-next-line */
        $record->condition = $model->condition;
        /** @phpstan-ignore-next-line */
        $record->perItemRate = $model->perItemRate;
        /** @phpstan-ignore-next-line */
        $record->weightRate = $model->weightRate;
        /** @phpstan-ignore-next-line */
        $record->percentageRate = $model->percentageRate;

        /** @phpstan-ignore-next-line */
        $record->save(false);
        /** @phpstan-ignore-next-line */
        $model->id = $record->id;

        return true;
    }

    public function deleteShippingRuleCategoryById(int $id): bool
    {
        /** @phpstan-ignore-next-line */
        $record = ShippingRuleCategoryRecord::findOne($id);

        if ($record) {
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
