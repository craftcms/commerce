<?php

namespace craft\commerce\elements\conditions\transfers;

use craft\elements\conditions\ElementCondition;
use craft\elements\conditions\StatusConditionRule;

/**
 * Transfer condition
 */
class TransferCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            StatusConditionRule::class,
        ]);
    }
}
