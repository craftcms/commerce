<?php

namespace craft\commerce\services;

use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Payment\Gateway\GatewayTypes;
use CraftCms\Yii2Adapter\Event\TypeRegistryCompatibility;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)` instead.
 */
class Gateways extends Component
{
    /** @deprecated in 6.0.0. Use `app(\CraftCms\Commerce\Payment\Gateway\GatewayTypes::class)->register()` instead. */
    public const EVENT_REGISTER_GATEWAY_TYPES = 'registerGatewayTypes';

    public const CONFIG_GATEWAY_KEY = \CraftCms\Commerce\Payment\Gateway\Gateways::CONFIG_GATEWAY_KEY;

    /**
     * @return string[]
     */
    public function getAllGatewayTypes(): array
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->getAllGatewayTypes();
    }

    /**
     * @return Collection<int, Gateway>
     */
    public function getAllCustomerEnabledGateways(): Collection
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->getAllCustomerEnabledGateways();
    }

    /**
     * @return Collection<int, Gateway>
     */
    public function getAllCustomerEnabledGatewaysAndAvailableForUseWithOrder(Order $order): Collection
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->getAllCustomerEnabledGatewaysAndAvailableForUseWithOrder($order);
    }

    /**
     * @return Collection<int, Gateway>
     */
    public function getAllGateways(): Collection
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->getAllGateways();
    }

    /**
     * @return Gateway[]
     */
    public function getAllArchivedGateways(): array
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->getAllArchivedGateways();
    }

    public function archiveGatewayById(int $id): bool
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->archiveGatewayById($id);
    }

    public function getGatewayById(int $id): ?Gateway
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->getGatewayById($id);
    }

    public function getGatewayByHandle(string $handle): ?Gateway
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->getGatewayByHandle($handle);
    }

    public function saveGateway(Gateway $gateway, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->saveGateway($gateway, $runValidation);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleChangedGateway(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->handleChangedGateway($event);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleArchivedGateway(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->handleArchivedGateway($event);
    }

    /**
     * @param int[] $ids
     */
    public function reorderGateways(array $ids): bool
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->reorderGateways($ids);
    }

    public function createGateway(string|array $config): Gateway
    {
        return app(\CraftCms\Commerce\Payment\Gateway\Gateways::class)->createGateway($config);
    }

    /** @internal */
    public static function finalizeRegistrationEvents(): void
    {
        TypeRegistryCompatibility::reconcile(app(GatewayTypes::class), \craft\commerce\Plugin::getInstance()->getGateways(), self::EVENT_REGISTER_GATEWAY_TYPES);
    }
}
