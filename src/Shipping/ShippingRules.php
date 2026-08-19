<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Shipping\Models\ShippingRule;
use CraftCms\Commerce\Shipping\Models\ShippingRuleCategory;
use CraftCms\Commerce\Shipping\Records\ShippingRule as ShippingRuleRecord;
use CraftCms\Commerce\Shipping\Records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use function CraftCms\Cms\t;

#[Singleton]
class ShippingRules
{
    private ?Collection $allShippingRules = null;

    /**
     * @return Collection<int, ShippingRule>
     */
    public function getAllShippingRules(): Collection
    {
        if ($this->allShippingRules !== null) {
            return $this->allShippingRules;
        }

        $rows = $this->query()->get()->all();
        $rules = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $row['orderCondition'] ??= '';
            $rules[] = new ShippingRule($row);
        }

        $this->allShippingRules = collect($rules);

        $this->eagerLoadShippingRuleCategories($this->allShippingRules);

        return $this->allShippingRules;
    }

    /**
     * @return Collection<int, ShippingRule>
     */
    public function getAllShippingRulesByShippingMethodId(int $methodId): Collection
    {
        return $this->getAllShippingRules()->where('methodId', $methodId);
    }

    public function getShippingRuleById(int $id): ?ShippingRule
    {
        return $this->getAllShippingRules()->firstWhere('id', $id);
    }

    public function saveShippingRule(ShippingRule $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = ShippingRuleRecord::find($model->id);
            if (!$record) {
                throw new \RuntimeException(t('No shipping rule exists with the ID "{id}"', ['id' => $model->id], category: 'commerce'));
            }
        } else {
            $record = new ShippingRuleRecord();
        }

        if ($runValidation && !$model->validate()) {
            return false;
        }

        $fields = [
            'name',
            'description',
            'methodId',
            'enabled',
            'orderConditionFormula',
            'baseRate',
            'perItemRate',
            'weightRate',
            'percentageRate',
            'minRate',
            'maxRate',
        ];

        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->orderCondition = $model->getOrderCondition()->getConfig();
        $record->customerCondition = $model->getCustomerCondition()->getConfig();

        if (empty($record->priority) && empty($model->priority)) {
            $count = ShippingRuleRecord::where('methodId', $model->methodId)->count();
            $record->priority = $model->priority = $count + 1;
        } elseif ($model->priority) {
            $record->priority = $model->priority;
        } else {
            $model->priority = $record->priority;
        }

        $record->save();
        $model->id = $record->id;

        ShippingRuleCategoryRecord::where('shippingRuleId', $model->id)->delete();

        foreach (app(ShippingCategories::class)->getAllShippingCategories($model->storeId) as $shippingCategory) {
            $ruleCategory = $model->getShippingRuleCategories()[$shippingCategory->id] ?? null;
            if ($ruleCategory) {
                $ruleCategory = new ShippingRuleCategory([
                    'shippingRuleId' => $model->id,
                    'shippingCategoryId' => $shippingCategory->id,
                    'condition' => $ruleCategory->condition,
                    'perItemRate' => $ruleCategory->perItemRate,
                    'weightRate' => $ruleCategory->weightRate,
                    'percentageRate' => $ruleCategory->percentageRate,
                ]);
            } else {
                $ruleCategory = new ShippingRuleCategory([
                    'shippingRuleId' => $model->id,
                    'shippingCategoryId' => $shippingCategory->id,
                    'condition' => ShippingRuleCategoryRecord::CONDITION_ALLOW,
                ]);
            }

            app(ShippingRuleCategories::class)->createShippingRuleCategory($ruleCategory, $runValidation);
        }

        $this->allShippingRules = null;

        return true;
    }

    public function reorderShippingRules(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            DB::table(Table::SHIPPINGRULES)->where('id', $id)->update(['priority' => $sortOrder + 1]);
        }

        $this->allShippingRules = null;

        return true;
    }

    public function deleteShippingRuleById(int $id): bool
    {
        $record = ShippingRuleRecord::find($id);

        if ($record) {
            $result = (bool) $record->delete();
            $this->allShippingRules = null;

            return $result;
        }

        return false;
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::table(Table::SHIPPINGRULES . ' as shippingrules')
            ->select([
                'methods.storeId',
                'shippingrules.id',
                'shippingrules.methodId',
                'shippingrules.name',
                'shippingrules.description',
                'shippingrules.enabled',
                'shippingrules.priority',
                'shippingrules.orderConditionFormula',
                'shippingrules.orderCondition',
                'shippingrules.customerCondition',
                'shippingrules.baseRate',
                'shippingrules.perItemRate',
                'shippingrules.weightRate',
                'shippingrules.percentageRate',
                'shippingrules.minRate',
                'shippingrules.maxRate',
            ])
            ->join(Table::SHIPPINGMETHODS . ' as methods', 'methods.id', '=', 'shippingrules.methodId')
            ->orderBy('shippingrules.methodId')
            ->orderBy('shippingrules.priority');
    }

    /**
     * @param Collection<int, ShippingRule> $shippingRules
     */
    private function eagerLoadShippingRuleCategories(Collection $shippingRules): void
    {
        $ruleIds = $shippingRules->pluck('id')->filter()->all();

        if (empty($ruleIds)) {
            return;
        }

        $categoriesByRuleId = app(ShippingRuleCategories::class)->getShippingRuleCategoriesByRuleIds($ruleIds);

        foreach ($shippingRules as $rule) {
            if ($rule->id !== null) {
                $rule->setShippingRuleCategories($categoriesByRuleId[$rule->id] ?? []);
            }
        }
    }
}
