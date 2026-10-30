<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Contracts;

use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Collection;

interface ShippingMethodInterface
{
    /** Returns the type of shipping method (e.g. the provider name). Shown in the control panel only. */
    public function getType(): string;

    public function getId(): ?int;

    public function getName(): string;

    public function getHandle(): string;

    public function getCpEditUrl(): string;

    /** @return Collection<ShippingRuleInterface> */
    public function getShippingRules(): Collection;

    public function getIsEnabled(): bool;

    public function getPriceForOrder(Order $order): float;

    public function getMatchingShippingRule(Order $order): ?ShippingRuleInterface;

    public function matchOrder(Order $order): bool;
}
