<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Order\Queries\OrderQuery;

use function CraftCms\Cms\t;

/**
 * @method array|string|null paramValue(?callable $normalizeValue = null)
 */
class OrderStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Order Status', category: 'commerce');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['orderStatus'];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $orderStatuses = app(OrderStatuses::class)->getAllOrderStatuses();

        /** @var OrderQuery $query */
        $query->orderStatus($this->paramValue(fn(string $value) => Arr::first($orderStatuses, fn(OrderStatus $status) => $status->uid === $value)?->handle));
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $orderStatusUid = $element->getOrderStatus()?->uid;

        return $this->matchValue($orderStatusUid);
    }

    protected function options(): array
    {
        return app(OrderStatuses::class)->getAllOrderStatuses()->mapWithKeys(fn(OrderStatus $status) => [$status->uid => $status->name])->all();
    }
}
