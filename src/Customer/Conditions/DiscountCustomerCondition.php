<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer\Conditions;

use CraftCms\Cms\User\Conditions\UserCondition;
use CraftCms\Cms\User\Elements\User;
use Override;

class DiscountCustomerCondition extends UserCondition
{
    #[Override]
    public ?string $elementType = User::class;

    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            HasOrdersConditionRule::class,
            SignedInConditionRule::class,
            DiscountGroupConditionRule::class,
        ]);
    }
}
