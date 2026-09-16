<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Commerce\Order\Queries\OrderQuery;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Order gateway/shipping-method scopes — see {@see QueriesOrderIdentity} for the trait convention
 * this follows.
 *
 * @internal
 */
trait QueriesOrderFulfillment
{
    public mixed $gatewayId = null;

    public mixed $shippingMethodHandle = null;

    protected function initQueriesOrderFulfillment(): void
    {
        $this->beforeQuery(static function(OrderQuery $orderQuery) {
            static::applyShippingMethodHandle($orderQuery, $orderQuery->shippingMethodHandle);
            static::applyGatewayId($orderQuery, $orderQuery->gatewayId);
        });
    }

    public static function applyShippingMethodHandle(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.shippingMethodHandle', $value);
    }

    public static function applyGatewayId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.gatewayId', $value);
    }

    public function shippingMethodHandle(mixed $value): static
    {
        $this->shippingMethodHandle = $value;
        return $this;
    }

    public function gateway(?GatewayInterface $value): static
    {
        $this->gatewayId = $value?->id;
        return $this;
    }

    public function gatewayId(mixed $value): static
    {
        $this->gatewayId = $value;
        return $this;
    }
}
