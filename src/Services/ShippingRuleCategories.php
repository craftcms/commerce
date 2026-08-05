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

        $this->allShippingRuleCategories = null;

        return true;
    }

    public function deleteShippingRuleCategoryById(int $id): bool
    {
        /** @phpstan-ignore-next-line */
        $record = ShippingRuleCategoryRecord::findOne($id);

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
