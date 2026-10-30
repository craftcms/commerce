<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static string[] getAllGatewayTypes()
 * @method static \Illuminate\Support\Collection getAllCustomerEnabledGateways()
 * @method static \Illuminate\Support\Collection getAllCustomerEnabledGatewaysAndAvailableForUseWithOrder(\CraftCms\Commerce\Order\Elements\Order $order)
 * @method static \Illuminate\Support\Collection getAllGateways()
 * @method static \CraftCms\Commerce\Payment\Gateway\Gateway[] getAllArchivedGateways()
 * @method static bool archiveGatewayById(int $id)
 * @method static \CraftCms\Commerce\Payment\Gateway\Gateway|null getGatewayById(int $id)
 * @method static \CraftCms\Commerce\Payment\Gateway\Gateway|null getGatewayByHandle(string $handle)
 * @method static bool saveGateway(\CraftCms\Commerce\Payment\Gateway\Gateway $gateway, bool $runValidation = true)
 * @method static void handleChangedGateway(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static void handleArchivedGateway(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool reorderGateways(int[] $ids)
 * @method static \CraftCms\Commerce\Payment\Gateway\Gateway createGateway(string|array $config)
 *
 * @see \CraftCms\Commerce\Payment\Gateway\Gateways
 */
class Gateways extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\Gateway\Gateways::class;
    }
}
