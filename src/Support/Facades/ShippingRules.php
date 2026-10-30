<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllShippingRules()
 * @method static \Illuminate\Support\Collection getAllShippingRulesByShippingMethodId(int $methodId)
 * @method static \CraftCms\Commerce\Shipping\Data\ShippingRule|null getShippingRuleById(int $id)
 * @method static bool saveShippingRule(\CraftCms\Commerce\Shipping\Data\ShippingRule $model, bool $runValidation = true)
 * @method static bool reorderShippingRules(array $ids)
 * @method static bool deleteShippingRuleById(int $id)
 *
 * @see \CraftCms\Commerce\Shipping\ShippingRules
 */
class ShippingRules extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Shipping\ShippingRules::class;
    }
}
