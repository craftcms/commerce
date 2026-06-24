<?php

namespace craft\commerce\elements\conditions\orders;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use Imagine\Exception\NotSupportedException;

/**
 * Shipping Zone Condition Zone.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class ShippingAddressZoneConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('commerce', 'Shipping Address Zone');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['shippingZone'];
    }

    /**
     * @inheritdoc
     */
    protected function options(): array
    {
        /** @var ShippingRuleOrderCondition $condition */
        $condition = $this->getCondition();

        return Plugin::getInstance()->getShippingZones()->getAllShippingZones($condition->storeId)->mapWithKeys(fn(ShippingAddressZone $zone) => [$zone->id => $zone->name])->all();
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new NotSupportedException('Shipping Address Zone condition rule does not support queries');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var ShippingRuleOrderCondition $condition */
        $condition = $this->getCondition();
        /** @var Order $element */
        $shippingAddress = $element->getShippingAddress() ?? $element->getEstimatedShippingAddress();

        if (!$shippingAddress) {
            return false;
        }

        /** @var ShippingAddressZone[] $shippingZones */
        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones($condition->storeId)->whereIn('id', $this->getValues())->all();

        // Start on `true` or `false` depending on the operator
        $match = $this->operator !== self::OPERATOR_IN;
        foreach ($shippingZones as $shippingZone) {
            if ($shippingZone->getCondition()->matchElement($shippingAddress)) {
                $match = $this->operator === self::OPERATOR_IN;
                break;
            }
        }

        return $match;
    }
}
