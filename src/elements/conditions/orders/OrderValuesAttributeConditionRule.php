<?php

namespace  craft\commerce\elements\conditions\orders;

use Craft;
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
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Order Value');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return [$this->orderAttribute];
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $field = $this->orderAttribute;
        return match ($this->operator) {
            '=' => $element->$field == $this->value,
            '!=' => $element->$field != $this->value,
            '<' => $element->$field < $this->value,
            '<=' => $element->$field <= $this->value,
            '>' => $element->$field > $this->value,
            '>=' => $element->$field >= $this->value,
            default => false,
        };
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->{$this->orderAttribute}($this->paramValue());
    }
}