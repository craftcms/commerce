<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\db\Table;
use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\Manual;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\records\Gateway as GatewayRecord;
use craft\db\Query;
use craft\errors\DeprecationException;
use craft\errors\MissingComponentException;
use craft\events\ConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTime;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;
use function get_class;

/**
 * Gateway service.
 *
 * @property GatewayInterface[] $allGateways all gateways
 * @property GatewayInterface[] $allCustomerEnabledGateways all gateways enabled for the customer
 * @property array $allSubscriptionGateways
 * @property string[] $allGatewayTypes all registered gateway types
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Gateways extends Component
{
    /**
     * @var array|null Gateway setting overrides
     */
    private ?array $_overrides = null;

    /**
     * @var Collection<Gateway>|null All gateways
     */
    private ?Collection $_allGateways = null;

    /**
     * @event RegisterComponentTypesEvent The event that is triggered for the registration of additional gateways.
     *
     * This example registers a custom gateway instance of the `MyGateway` class:
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(
     *     Gateways::class,
     *     Gateways::EVENT_REGISTER_GATEWAY_TYPES,
     *     function(RegisterComponentTypesEvent $event) {
     *         $event->types[] = MyGateway::class;
     *     }
     * );
     * ```
     */
    public const EVENT_REGISTER_GATEWAY_TYPES = 'registerGatewayTypes';

    public const CONFIG_GATEWAY_KEY = 'commerce.gateways';


    /**
     * Returns all registered gateway types.
     *
     * @return string[]
     */
    public function getAllGatewayTypes(): array
    {
        $gatewayTypes = [
            Dummy::class,
            Manual::class,
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $gatewayTypes,
        ]);
        $this->trigger(self::EVENT_REGISTER_GATEWAY_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all customer enabled gateways.
     *
     * @return Collection All gateways that are enabled for frontend
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getAllCustomerEnabledGateways(): Collection
    {
        return $this->getAllGateways()->where(fn(GatewayInterface $gateway) => $gateway->getIsFrontendEnabled());
    }

    /**
     * Returns all subscription gateways.
     *
     * @return Collection<GatewayInterface> All Subscription gateways
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getAllSubscriptionGateways(): Collection
    {
        return $this->getAllGateways()->where(fn(GatewayInterface $gateway) => $gateway instanceof SubscriptionGateway);
    }

    /**
     * Returns all gateways
     *
     * @return Collection All gateways
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getAllGateways(): Collection
    {
        return $this->_getAllGateways()->where('isArchived', false);
    }

    /**
     * Archives a gateway by its ID.
     *
     * @param int $id gateway ID
     * @return bool Whether the archiving was successful or not
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @throws \yii\db\Exception
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
        Craft::$app->getDb()->createCommand()
            ->delete(Table::PAYMENTSOURCES, ['gatewayId' => $id])
            ->execute();

        // Clear this as the selected gateway from all active carts and orders
        Craft::$app->getDb()->createCommand()
            ->update(Table::ORDERS,
                [
                    'gatewayId' => null,
                    'paymentSourceId' => null,
                ],
                [
                    'gatewayId' => $id,
                ], [], false)
            ->execute();


        return true;
    }

    /**
     * Returns a gateway by its ID.
     *
     * @param int $id
     * @return Gateway|null The gateway or null if not found.
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getGatewayById(int $id): ?Gateway
    {
        return $this->_getAllGateways()->firstWhere('id', $id);
    }

    /**
     * Returns a gateway by its handle.
     *
     * @param string $handle
     * @return Gateway|null The gateway or null if not found.
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function getGatewayByHandle(string $handle): ?Gateway
    {
        return $this->getAllGateways()->firstWhere('handle', $handle);
    }

    /**
     * Saves a gateway.
     *
     * @param Gateway $gateway The gateway to be saved.
     * @param bool $runValidation Whether the gateway should be validated
     * @return bool Whether the gateway was saved successfully or not.
     * @throws Exception
     * @throws InvalidConfigException
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function saveGateway(Gateway $gateway, bool $runValidation = true): bool
    {
        $isNewGateway = $gateway->getIsNew();

        if ($runValidation && !$gateway->validate()) {
            Craft::info('Gateway not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewGateway) {
            $gatewayUid = StringHelper::UUID();
        } else {
            $gatewayUid = $gateway->uid;
        }

        $existingGateway = $this->getGatewayByHandle($gateway->handle);

        if ($existingGateway && (!$gateway->id || $gateway->id != $existingGateway->id)) {
            $gateway->addError('handle', Craft::t('commerce', 'That handle is already in use.'));
            return false;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        if ($gateway->isArchived) {
            $configData = null;
        } else {
            $configData = [
                'name' => $gateway->name,
                'handle' => $gateway->handle,
                'type' => get_class($gateway),
                'settings' => $gateway->getSettings(),
                'sortOrder' => ($gateway->sortOrder ?? 99),
                'paymentType' => $gateway->paymentType,
                'isFrontendEnabled' => $gateway->getIsFrontendEnabled(false),
            ];
        }

        $configPath = self::CONFIG_GATEWAY_KEY . '.' . $gatewayUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewGateway) {
            $gateway->id = Db::idByUid(Table::GATEWAYS, $gatewayUid);
        }

        $this->_allGateways = null; // reset cache

        return true;
    }

    /**
     * Handle gateway change
     *
     * @throws Throwable if reasons
     */
    public function handleChangedGateway(ConfigEvent $event): void
    {
        $gatewayUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
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
            $gatewayRecord->isArchived = false;
            $gatewayRecord->dateArchived = null;
            $gatewayRecord->uid = $gatewayUid;

            // Save the volume
            $gatewayRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Handle gateway being archived
     *
     * @throws Throwable if reasons
     */
    public function handleArchivedGateway(ConfigEvent $event): void
    {
        $gatewayUid = $event->tokenMatches[0];

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $gatewayRecord = $this->_getGatewayRecord($gatewayUid);

            $gatewayRecord->isArchived = true;
            $gatewayRecord->dateArchived = Db::prepareDateForDb(new DateTime());

            // Save the volume
            $gatewayRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Reorders gateways by ids.
     *
     * @param array $ids Array of gateways.
     * @return bool Always true.
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function reorderGateways(array $ids): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $uidsByIds = Db::uidsByIds(Table::GATEWAYS, $ids);

        foreach ($ids as $gatewayOrder => $gatewayId) {
            if (!empty($uidsByIds[$gatewayId])) {
                $gatewayUid = $uidsByIds[$gatewayId];
                $projectConfig->set(self::CONFIG_GATEWAY_KEY . '.' . $gatewayUid . '.sortOrder', $gatewayOrder + 1);
            }
        }

        $this->_allGateways = null; // reset cache

        return true;
    }

    /**
     * Creates a gateway with a given config
     *
     * @param string|array $config The gateway’s class name, or its config, with a `type` value and optionally a `settings` value
     * @return Gateway The gateway
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    public function createGateway(string|array $config): Gateway
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        // Are they overriding any settings?
        if (!empty($config['handle']) && ($override = $this->getGatewayOverrides($config['handle'])) !== null) {
            // Save a reference to the original config in case the gateway type is missing
            $originalConfig = $config;

            // Apply the settings early so the overrides don't get overridden
            $config = array_merge(ComponentHelper::mergeSettings($config), $override);
        }

        try {
            if ($config['type'] == MissingGateway::class) {
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

    /**
     * Returns any custom gateway settings form config file.
     *
     * @param string $handle The gateway handle
     * @throws DeprecationException
     * @deprecated in 3.3. Overriding gateway settings using the `commerce-gateways.php` file has been deprecated. Use the gateway’s config file instead.
     */
    public function getGatewayOverrides(string $handle): ?array
    {
        if ($this->_overrides === null) {
            $this->_overrides = Craft::$app->getConfig()->getConfigFromFile('commerce-gateways');
        }

        $overrides = $this->_overrides[$handle] ?? null;

        if ($overrides != null) {
            Craft::$app->getDeprecator()->log('craft.commerce.gateways.getGatewayOverrides()', 'Overriding gateway settings using the `commerce-gateways.php` file has been deprecated. Use the gateway’s config file instead.');
        }

        return $overrides;
    }


    /**
     * Returns a Query object prepped for retrieving gateways.
     *
     * @return Query The query object.
     */
    private function _createGatewayQuery(): Query
    {
        return (new Query())
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
            ->orderBy(['sortOrder' => SORT_ASC])
            ->from([Table::GATEWAYS]);
    }

    /**
     * Gets a gateway's record by uid.
     */
    private function _getGatewayRecord(string $uid): GatewayRecord
    {
        if ($gateway = GatewayRecord::findOne(['uid' => $uid])) {
            return $gateway;
        }

        return new GatewayRecord();
    }

    /**
     * @return Collection<Gateway>
     * @throws DeprecationException
     * @throws InvalidConfigException
     */
    private function _getAllGateways(): Collection
    {
        if ($this->_allGateways === null) {
            $results = $this->_createGatewayQuery()
                ->all();

            if ($this->_allGateways === null) {
                $this->_allGateways = collect();
            }

            $gateways = [];
            foreach ($results as $result) {
                $gateways[] = $this->createGateway($result);
            }

            $this->_allGateways = collect($gateways)->keyBy('id');
        }

        return $this->_allGateways;
    }
}
