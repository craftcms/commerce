<?php

namespace craft\commerce\elements\conditions\orders;

use craft\base\conditions\BaseNumberConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

/**
 * Order Number Attribute Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 *
 * @property-read float|int $orderAttributeValue
 */
abstract class OrderValuesAttributeConditionRule extends BaseNumberConditionRule implements ElementConditionRuleInterface
{
    public string $orderAttribute = '';

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return 'Label not implemented';
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->{$this->orderAttribute});
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->{$this->orderAttribute}($this->paramValue());
    }
}
