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
use craft\commerce\Plugin;
use craft\commerce\records\Gateway as GatewayRecord;
use craft\db\Query;
use craft\errors\MissingComponentException;
use craft\events\ConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use function get_class;

/**
 * Gateway service.
 *
 * @property GatewayInterface[] $allFrontEndGateways all frontend enabled gateways
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
    private $_overrides;

    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent The event that is triggered when registering gateways.
     *
     * Plugins can register their own gateways.
     *
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\commerce\services\Purchasables;
     * use yii\base\Event;
     *
     * Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES, function(RegisterComponentTypesEvent $e) {
     *     $e->types[] = MyGateway::class;
     * });
     * ```
     */
    const EVENT_REGISTER_GATEWAY_TYPES = 'registerGatewayTypes';

    const CONFIG_GATEWAY_KEY = 'commerce.gateways';

    // Public Methods
    // =========================================================================

    /**
     * Returns all registered gateway types.
     *
     * @return string[]
     */
    public function getAllGatewayTypes(): array
    {
        $gatewayTypes = [
            Dummy::class,
            Manual::class
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $gatewayTypes
        ]);
        $this->trigger(self::EVENT_REGISTER_GATEWAY_TYPES, $event);

        return $event->types;
    }

    /**
     * Returns all frontend enabled gateways.
     *
     * @return GatewayInterface[] All gateways that are enabled for frontend
     * @deprecated as of 2.0
     */
    public function getAllFrontEndGateways(): array
    {
        return $this->getAllCustomerEnabledGateways();
    }

    /**
     * Returns all customer enabled gateways.
     *
     * @return GatewayInterface[] All gateways that are enabled for frontend
     */
    public function getAllCustomerEnabledGateways(): array
    {
        $rows = $this->_createGatewayQuery()
            ->where(['or', ['isArchived' => null], ['not', ['isArchived' => true]]])
            ->andWhere(['isFrontendEnabled' => true])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        $gateways = [];

        foreach ($rows as $row) {
            $gateways[$row['id']] = $this->createGateway($row);
        }

        // Filter gateways to respect custom config files settings `isFrontendEnabled` to `false`
        $gateways = ArrayHelper::where($gateways, 'isFrontendEnabled', true);

        return $gateways;
    }

    /**
     * Returns all subscription gateways.
     *
     * @return array
     */
    public function getAllSubscriptionGateways(): array
    {
        $gateways = $this->getAllGateways();
        $subscriptionGateways = [];

        foreach ($gateways as $gateway) {
            if ($gateway instanceof SubscriptionGateway) {
                $subscriptionGateways[] = $gateway;
            }
        }

        return $subscriptionGateways;
    }

    /**
     * Returns  all gateways
     *
     * @return GatewayInterface[] All gateways
     */
    public function getAllGateways(): array
    {
        $rows = $this->_createGatewayQuery()
            ->where(['or', ['isArchived' => null], ['not', ['isArchived' => true]]])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        $gateways = [];

        foreach ($rows as $row) {
            $gateways[$row['id']] = $this->createGateway($row);
        }

        return $gateways;
    }

    /**
     * Archives a gateway by its ID.
     *
     * @param int $id gateway ID
     * @return bool Whether the archiving was successful or not
     */
    public function archiveGatewayById(int $id): bool
    {
        /** @var Gateway $gateway */
        $gateway = $this->getGatewayById($id);
        $gateway->isArchived = true;

        return $this->saveGateway($gateway);
    }

    /**
     * Returns a gateway by its ID.
     *
     * @param int $id
     * @return GatewayInterface|null The gateway or null if not found.
     */
    public function getGatewayById(int $id)
    {
        $result = $this->_createGatewayQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? $this->createGateway($result) : null;
    }

    /**
     * Returns a gateway by its handle.
     *
     * @param string $handle
     * @return Gateway|GatewayInterface|null The gateway or null if not found.
     */
    public function getGatewayByHandle(string $handle)
    {
        $result = $this->_createGatewayQuery()
            ->where(['handle' => $handle])
            ->andWhere(['or', ['isArchived' => null], ['not', ['isArchived' => true]]])
            ->one();

        return $result ? $this->createGateway($result) : null;
    }

    /**
     * Saves a gateway.
     *
     * @param Gateway $gateway The gateway to be saved.
     * @param bool $runValidation Whether the gateway should be validated
     * @return bool Whether the gateway was saved successfully or not.
     * @throws Exception
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
            $gateway->addError('handle', Plugin::t( 'That handle is already in use.'));
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
                'sortOrder' => (int)($gateway->sortOrder ?? 99),
                'paymentType' => $gateway->paymentType,
                'isFrontendEnabled' => (bool)$gateway->isFrontendEnabled,
            ];
        }

        $configPath = self::CONFIG_GATEWAY_KEY . '.' . $gatewayUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewGateway) {
            $gateway->id = Db::idByUid(Table::GATEWAYS, $gatewayUid);
        }

        return true;
    }

    /**
     * Handle gateway change
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleChangedGateway(ConfigEvent $event)
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
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleArchivedGateway(ConfigEvent $event)
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

        return true;
    }

    /**
     * Creates a gateway with a given config
     *
     * @param mixed $config The gateway’s class name, or its config, with a `type` value and optionally a `settings` value
     * @return GatewayInterface The gateway
     */
    public function createGateway($config): GatewayInterface
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

            $config = $originalConfig ?? $config;

            $gateway = new MissingGateway($config);
        }

        return $gateway;
    }

    /**
     * Returns any custom gateway settings form config file.
     *
     * @param string $handle The gateway handle
     * @return array|null
     */
    public function getGatewayOverrides(string $handle)
    {
        if ($this->_overrides === null) {
            $this->_overrides = Craft::$app->getConfig()->getConfigFromFile('commerce-gateways');
        }

        return $this->_overrides[$handle] ?? null;
    }

    // Private methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving gateways.
     *
     * @return Query The query object.
     */
    private function _createGatewayQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'type',
                'name',
                'handle',
                'paymentType',
                'isFrontendEnabled',
                'isArchived',
                'dateArchived',
                'settings',
                'uid',
                'sortOrder'
            ])
            ->from([Table::GATEWAYS]);
    }

    /**
     * Gets a gateway's record by uid.
     *
     * @param string $uid
     * @return GatewayRecord
     */
    private function _getGatewayRecord(string $uid): GatewayRecord
    {
        if ($gateway = GatewayRecord::findOne(['uid' => $uid])) {
            return $gateway;
        }

        return new GatewayRecord();
    }
}
