<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Widgets\Concerns;

use craft\commerce\Plugin;
use CraftCms\Commerce\Store\Concerns\StoreTrait;

trait StatWidgetTrait
{
    use StoreTrait;

    public mixed $startDate = null;

    public mixed $endDate = null;

    public ?string $dateRange = null;

    public ?array $orderStatuses = null;

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getOrderStatusOptions(): array
    {
        $orderStatuses = [];

        foreach (Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($this->storeId) as $orderStatus) {
            $orderStatuses[] = [
                'label' => $orderStatus->name,
                'value' => $orderStatus->uid,
            ];
        }

        return $orderStatuses;
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    public function getStoreOptions(): array
    {
        return Plugin::getInstance()->getStores()->getAllStores()->map(fn($store) => [
            'label' => $store->getName(),
            'value' => $store->id,
        ])->all();
    }
}
