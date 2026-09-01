<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Data\ShippingAddressZone;
use CraftCms\Commerce\Shipping\ShippingZones;
use LogicException;
use Override;

use function CraftCms\Cms\t;

class ShippingAddressZoneConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Shipping Address Zone', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['shippingZone'];
    }

    protected function options(): array
    {
        /** @var ShippingRuleOrderCondition $condition */
        $condition = $this->getCondition();

        return app(ShippingZones::class)->getAllShippingZones($condition->storeId)->mapWithKeys(fn(ShippingAddressZone $zone) => [$zone->id => $zone->name])->all();
    }

    #[Override]
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new LogicException('Shipping Address Zone condition rule does not support queries');
    }

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
        $shippingZones = app(ShippingZones::class)->getAllShippingZones($condition->storeId)->whereIn('id', $this->getValues())->all();

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
