<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Address\Conditions;

use CraftCms\Cms\Address\Conditions\AddressCondition;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use LogicException;
use Override;

class DiscountAddressCondition extends AddressCondition
{
    #[Override]
    public ?string $elementType = Address::class;

    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            PostalCodeFormulaConditionRule::class,
        ]);
    }

    #[Override]
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new LogicException('Discount Address Condition does not support element queries.');
    }
}
