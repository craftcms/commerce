<?php

namespace craft\commerce\elements\conditions\variants;

use craft\elements\conditions\ElementCondition;

/**
 * Variant query condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class VariantCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), []);
    }
}
