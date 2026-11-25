<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

/**
 * Line Item SKU condition rule.
 *
 * Matches orders that have at least one line item with a matching SKU.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class LineItemSkuConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Line Item SKU');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['lineItemSku'];
    }

    /**
     * @inheritdoc
     */
    protected function operators(): array
    {
        // Negated and empty operators are left out, since “has a line item that isn’t X”
        // reads like “has no line item that is X”.
        return [
            self::OPERATOR_EQ,
            self::OPERATOR_BEGINS_WITH,
            self::OPERATOR_ENDS_WITH,
            self::OPERATOR_CONTAINS,
            self::OPERATOR_IN,
        ];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        $value = $this->paramValue();
        if (empty($value)) {
            return;
        }

        /** @var OrderQuery $query */
        $query->lineItemSku($value);
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        if (empty($this->paramValue())) {
            return true;
        }

        /** @var Order $element */
        $lineItems = $element->getLineItems();

        foreach ($lineItems as $lineItem) {
            if ($this->matchValue($lineItem->sku)) {
                return true;
            }
        }

        return false;
    }
}
