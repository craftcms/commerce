<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderAdjustments;
use LogicException;
use Override;

use function CraftCms\Cms\t;

class DiscountedItemSubtotalConditionRule extends OrderCurrencyValuesAttributeConditionRule
{
    #[Override]
    public string $orderAttribute = 'itemSubtotal';

    #[Override]
    public function getLabel(): string
    {
        return t('Discounted Item Subtotal', category: 'commerce');
    }

    #[Override]
    public function modifyQuery(ElementQueryInterface $query): void
    {
        throw new LogicException('Discounted Item Subtotal condition rule does not support queries');
    }

    #[Override]
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $discountAdjustments = [];
        $discountAdjusters = app(OrderAdjustments::class)->getDiscountAdjusters();
        foreach ($discountAdjusters as $discountAdjuster) {
            $adjuster = new $discountAdjuster();
            $discountAdjustments = array_merge($discountAdjustments, $adjuster->adjust($element));
        }

        $discountAmount = 0;
        foreach ($discountAdjustments as $adjustment) {
            $discountAmount += $adjustment->amount;
        }

        $itemTotal = $element->getItemSubtotal() + $discountAmount;

        return $this->matchValue($itemTotal);
    }
}
