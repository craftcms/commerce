<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use LogicException;
use Override;

class DiscountOrderCondition extends OrderCondition implements HasStoreInterface
{
    use StoreTrait;

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'storeId' => ['nullable', 'integer'],
        ]);
    }

    #[Override]
    protected function config(): array
    {
        return array_merge(parent::config(), ['storeId' => $this->storeId]);
    }

    #[Override]
    protected function selectableConditionRules(): array
    {
        $rules = parent::selectableConditionRules();

        // We don't need the condition to have the coupon code rule
        return Arr::where($rules, fn($rule) => $rule !== CouponCodeConditionRule::class);
    }

    #[Override]
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new LogicException('Discount Order Condition does not support element queries.');
    }
}
