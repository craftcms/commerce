<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllShippingMethods(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Shipping\Data\ShippingMethod|null getShippingMethodByHandle(string $handle, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Shipping\Data\ShippingMethod|null getShippingMethodById(int $id, int|null $storeId = null)
 * @method static array getMatchingShippingMethods(\CraftCms\Commerce\Order\Elements\Order $order)
 * @method static array getSerializedOrderForMatchingRules(\CraftCms\Commerce\Order\Elements\Order $order)
 * @method static \CraftCms\Commerce\Shipping\Contracts\ShippingRuleInterface|null getMatchingShippingRule(\CraftCms\Commerce\Order\Elements\Order $order, \CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface $method)
 * @method static bool saveShippingMethod(\CraftCms\Commerce\Shipping\Data\ShippingMethod $model, bool $runValidation = true)
 * @method static bool deleteShippingMethodById(int $id)
 * @method static void clearCache()
 *
 * @see \CraftCms\Commerce\Shipping\ShippingMethods
 */
class ShippingMethods extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Shipping\ShippingMethods::class;
    }
}
