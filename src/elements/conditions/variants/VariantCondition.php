<?php

namespace craft\commerce\elements\conditions\variants;

use craft\commerce\elements\Variant;
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
    public ?string $elementType = Variant::class;

    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
        ]);
    }
}
