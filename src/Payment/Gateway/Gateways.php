<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Gateway;

use Carbon\Carbon;
use craft\events\ConfigEvent;
use craft\helpers\Db as CraftDb;
use CraftCms\Cms\Component\ComponentHelper;
use CraftCms\Cms\Component\Exceptions\MissingComponentException;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Gateway\Models\Gateway as GatewayRecord;
use CraftCms\Commerce\Payment\Gateway\Types\MissingGateway;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use function CraftCms\Cms\t;

#[Singleton]
class Gateways
{
    public const string CONFIG_GATEWAY_KEY = 'commerce.gateways';

    /**
     * @var Collection<int, Gateway>|null All gateways
     */
    private ?Collection $allGateways = null;

    /**
     * Returns all registered gateway types.
     *
     * @return string[]
     */
    public function getAllGatewayTypes(): array
    {
        return app(GatewayTypes::class)->types()->all();
    }

    /**
     * Returns all customer enabled gateways.
     *
     * @return Collection<int, Gateway> All gateways that are enabled for frontend
     */
    public function getAllCustomerEnabledGateways(): Collection
    {
        return $this->getAllGateways()->filter(fn(Gateway $gateway) => $gateway->getIsFrontendEnabled());
    }

    /**
     * Returns all customer enabled gateways and allowed for the order/cart.
     *
     * @return Collection<int, Gateway> All gateways that are enabled for frontend and allowed for the order/cart.
     */
    public function getAllCustomerEnabledGatewaysAndAvailableForUseWithOrder(Order $order): Collection
    {
        return $this->getAllCustomerEnabledGateways()->filter(fn(Gateway $gateway) => $gateway->availableForUseWithOrder($order));
    }

    /**
     * Returns all gateways.
     *
     * @return Collection<int, Gateway> All gateways
     */
    public function getAllGateways(): Collection
    {
        return $this->_getAllGateways()->where('isArchived', false);
    }

    /**
     * @return Gateway[]
     */
    public function getAllArchivedGateways(): array
    {
        return $this->_getAllGateways()->where('isArchived', true)->all();
    }

    /**
     * Archives a gateway by its ID.
     *
     * @return bool Whether the archiving was successful or not
     */
    public function archiveGatewayById(int $id): bool
    {
        /** @var Gateway $gateway */
        $gateway = $this->getGatewayById($id);
        $gateway->isArchived = true;

        if (!$this->saveGateway($gateway)) {
            return false;
        }

        // remove all payment sources for this gateway
        // this will also remove them as the payment source for a cart
        DB::table(Table::PAYMENTSOURCES)->where('gatewayId', $id)->delete();

        // Clear this as the selected gateway from all active carts and orders
        DB::table(Table::ORDERS)->where('gatewayId', $id)->update([
            'gatewayId' => null,
            'paymentSourceId' => null,
        ]);

        return true;
    }

    /**
     * Returns a gateway by its ID.
     */
    public function getGatewayById(int $id): ?Gateway
    {
        return $this->_getAllGateways()->firstWhere('id', $id);
    }

    /**
     * Returns a gateway by its handle.
     */
    public function getGatewayByHandle(string $handle): ?Gateway
    {
        return $this->_getAllGateways()->firstWhere('handle', $handle);
    }

    /**
     * Saves a gateway.
     */
    public function saveGateway(Gateway $gateway, bool $runValidation = true): bool
    {
        $isNewGateway = $gateway->getIsNew();

        if ($runValidation && !$gateway->validate()) {
            Log::info('Gateway not saved due to validation error.');

            return false;
        }

        $gatewayUid = $isNewGateway ? Str::uuid()->toString() : $gateway->uid;

        $existingGateway = $this->getGatewayByHandle($gateway->handle);

        if ($existingGateway && (!$gateway->id || $gateway->id != $existingGateway->id)) {
            $gateway->addError('handle', t('That handle is already in use.', category: 'commerce'));

            return false;
        }

        $configData = $gateway->isArchived ? null : $gateway->getConfig();

        $configPath = self::CONFIG_GATEWAY_KEY . '.' . $gatewayUid;
        ProjectConfig::set($configPath, $configData);

        if ($isNewGateway) {
            $gateway->id = CraftDb::idByUid(Table::GATEWAYS, $gatewayUid);
        }

        $this->allGateways = null; // reset cache

        return true;
    }

    /**
     * Handle gateway change.
     *
     * @throws Throwable if reasons
     */
    public function handleChangedGateway(ConfigEvent $event): void
    {
        $gatewayUid = $event->tokenMatches[0];
        $data = $event->newValue;

        // Bail if the data is not a valid gateway config array
        if (!is_array($data)) {
            return;
        }

        DB::beginTransaction();
        try {
            $gatewayRecord = $this->_getGatewayRecord($gatewayUid);

            $gatewayRecord->name = $data['name'];
            $gatewayRecord->handle = $data['handle'];
            $gatewayRecord->type = $data['type'];
            $gatewayRecord->settings = $data['settings'] ?? null;
            $gatewayRecord->sortOrder = $data['sortOrder'];
            $gatewayRecord->paymentType = $data['paymentType'];
            if ($data['isFrontendEnabled'] === null || is_bool($data['isFrontendEnabled'])) {
                $data['isFrontendEnabled'] = $data['isFrontendEnabled'] ? '1' : '0';
            }

            $gatewayRecord->isFrontendEnabled = $data['isFrontendEnabled'];
            $gatewayRecord->orderCondition = $data['orderCondition'] ?? null;
            $gatewayRecord->billingAddressCondition = $data['billingAddressCondition'] ?? null;
            $gatewayRecord->shippingAddressCondition = $data['shippingAddressCondition'] ?? null;
            $gatewayRecord->isArchived = false;
            $gatewayRecord->dateArchived = null;
            $gatewayRecord->uid = $gatewayUid;

            $gatewayRecord->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle gateway being archived.
     *
     * @throws Throwable if reasons
     */
    public function handleArchivedGateway(ConfigEvent $event): void
    {
        $gatewayUid = $event->tokenMatches[0];

        DB::beginTransaction();
        try {
            $gatewayRecord = $this->_getGatewayRecord($gatewayUid);

            $gatewayRecord->isArchived = true;
            $gatewayRecord->dateArchived = Carbon::now();

            $gatewayRecord->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reorders gateways by ids.
     *
     * @param int[] $ids Array of gateway IDs.
     * @return bool Always true.
     */
    public function reorderGateways(array $ids): bool
    {
        $uidsByIds = CraftDb::uidsByIds(Table::GATEWAYS, $ids);

        foreach ($ids as $gatewayOrder => $gatewayId) {
            if (!empty($uidsByIds[$gatewayId])) {
                $gatewayUid = $uidsByIds[$gatewayId];
                ProjectConfig::set(self::CONFIG_GATEWAY_KEY . '.' . $gatewayUid . '.sortOrder', $gatewayOrder + 1);
            }
        }

        $this->allGateways = null; // reset cache

        return true;
    }

    /**
     * Creates a gateway with a given config.
     *
     * @param string|array $config The gateway's class name, or its config, with a `type` value and optionally a `settings` value
     */
    public function createGateway(string|array $config): Gateway
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        try {
            if ($config['type'] === MissingGateway::class) {
                throw new MissingComponentException('Missing Gateway Class.');
            }

            /** @var Gateway $gateway */
            $gateway = ComponentHelper::createComponent($config, GatewayInterface::class);
        } catch (MissingComponentException $e) {
            $config['errorMessage'] = $e->getMessage();
            $config['expectedType'] = $config['type'];
            unset($config['type']);

            $gateway = new MissingGateway($config);
        }

        return $gateway;
    }

    private function query(): Builder
    {
        $query = DB::table(Table::GATEWAYS)
            ->select([
                'dateArchived',
                'handle',
                'id',
                'isArchived',
                'isFrontendEnabled',
                'name',
                'paymentType',
                'settings',
                'sortOrder',
                'type',
                'uid',
            ])
            ->orderBy('sortOrder');

        // TODO: Remove these hasColumn checks in Commerce 6.0 once the schema guarantees orderCondition / billingAddressCondition / shippingAddressCondition columns on the gateways table
        if (Schema::hasColumn(Table::GATEWAYS, 'orderCondition')) {
            $query->addSelect('orderCondition');
        }
        if (Schema::hasColumn(Table::GATEWAYS, 'billingAddressCondition')) {
            $query->addSelect('billingAddressCondition');
        }
        if (Schema::hasColumn(Table::GATEWAYS, 'shippingAddressCondition')) {
            $query->addSelect('shippingAddressCondition');
        }

        return $query;
    }

    /**
     * Gets a gateway's record by uid.
     */
    private function _getGatewayRecord(string $uid): GatewayRecord
    {
        if ($gateway = GatewayRecord::where('uid', $uid)->first()) {
            return $gateway;
        }

        return new GatewayRecord();
    }

    /**
     * @return Collection<int, Gateway>
     */
    private function _getAllGateways(): Collection
    {
        if ($this->allGateways === null) {
            $results = $this->query()->get();

            $gateways = [];
            foreach ($results as $result) {
                $gateways[] = $this->createGateway((array)$result);
            }

            $this->allGateways = collect($gateways)->keyBy('id');
        }

        return $this->allGateways;
    }
}
