<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use LogicException;
use Override;

class ShippingRuleOrderCondition extends OrderCondition implements HasStoreInterface
{
    use StoreTrait;

    #[Override]
    public ?string $elementType = Order::class;

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
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new LogicException('Shipping Rule Order Condition does not support queries');
    }

    #[Override]
    protected function selectableConditionRules(): array
    {
        $ruleTypes = parent::selectableConditionRules();

        foreach ($ruleTypes as $key => $ruleType) {
            if (in_array($ruleType, [
                CompletedConditionRule::class,
                DateOrderedConditionRule::class,
                PaidConditionRule::class,
                OrderStatusConditionRule::class,
                ShippingMethodConditionRule::class,
                TotalPaidConditionRule::class,
            ])) {
                unset($ruleTypes[$key]);
            }
        }

        $ruleTypes[] = DiscountedItemSubtotalConditionRule::class;
        $ruleTypes[] = ShippingAddressZoneConditionRule::class;

        return $ruleTypes;
    }
}
