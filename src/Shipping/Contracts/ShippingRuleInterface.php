<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Contracts;

use craft\commerce\elements\Order;

interface ShippingRuleInterface
{
    public function matchOrder(Order $order): bool;

    public function getIsEnabled(): bool;

    public function getOptions(): mixed;

    public function getPercentageRate(?int $shippingCategoryId): float;

    public function getPerItemRate(?int $shippingCategoryId): float;

    public function getWeightRate(?int $shippingCategoryId): float;

    public function getBaseRate(): float;

    public function getMaxRate(): float;

    public function getMinRate(): float;

    public function getDescription(): string;
}
