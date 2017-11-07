<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\Plugin;
use craft\commerce\records\Gateway as GatewayRecord;
use craft\db\Query;
use craft\errors\MissingComponentException;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\Db;
use yii\base\Component;
use yii\base\Exception;

/**
 * Gateway service.
 *
 * @property GatewayInterface[]|array $allFrontEndGateways
 * @property GatewayInterface[]|array $allGateways
 * @property string[]                 $allGatewayTypes
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Gateways extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterComponentTypesEvent The event that is triggered when registering gateways.
     */
    const EVENT_REGISTER_GATEWAY_TYPES = 'registerGatewayTypes';

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
            Dummy::class
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $gatewayTypes
        ]);
        $this->trigger(self::EVENT_REGISTER_GATEWAY_TYPES, $event);

        return $event->types;
    }

    /**
     * Get all frontend enabled gateways.
     *
     * @return GatewayInterface[] All gateways that are enabled for frontend
     */
    public function getAllFrontEndGateways(): array
    {
        $rows = $this->_createGatewayQuery()
            ->where(['or', ['isArchived' => null], ['not', ['isArchived' => true]]])
            ->andWhere(['frontendEnabled' => true])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        $gateways = [];

        foreach ($rows as $row) {
            $gateways[$row['id']] = $this->createGateway($row);
        }

        return $gateways;
    }

    /**
     * Get all gateways
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
     * Archive a gateway by it's id.
     *
     * @param int $id gateway id.
     *
     * @return bool Whether the archiving was successful or not
     */
    public function archiveGatewayById(int $id): bool
    {
        /** @var Gateway $gateway */
        $gateway = $this->getGatewayById($id);
        $gateway->isArchived = true;
        $gateway->dateArchived = Db::prepareDateForDb(new \DateTime());

        return $this->saveGateway($gateway);
    }

    /**
     * Get a gateway by it's id.
     *
     * @param int $id
     *
     * @return GatewayInterface|null The gateway or null if not found.
     */
    public function getGatewayById(int $id)
    {
        $row = $this->_createGatewayQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }

        return $this->createGateway($row);
    }

    /**
     * Save a gateway.
     *
     * @param Gateway $gateway       The gateway to be saved.
     * @param bool    $runValidation Whether the gateway should be validated
     *
     * @return bool Whether the gateway was saved successfully or not.
     * @throws Exception
     */
    public function saveGateway(Gateway $gateway, bool $runValidation = true): bool
    {
        if ($gateway->id) {
            $record = GatewayRecord::findOne($gateway->id);

            if (!$record) {
                throw new Exception(\Craft::t('commerce', 'No gateway exists with the ID “{id}”', ['id' => $gateway->id]));
            }
        } else {
            $record = new GatewayRecord();
        }

        if ($runValidation && !$gateway->validate()) {
            Craft::info('Gateway not saved due to validation error.', __METHOD__);
            return false;
        }

        $record->settings = $gateway->settings;
        $record->name = $gateway->name;
        $record->handle = $gateway->handle;
        $record->paymentType = $gateway->paymentType;
        $record->type = get_class($gateway);
        $record->frontendEnabled = $gateway->frontendEnabled;
        $record->sendCartInfo = $gateway->sendCartInfo;
        $record->isArchived = $gateway->isArchived;
        $record->dateArchived = $gateway->dateArchived;

        $record->validate();
        $gateway->addErrors($record->getErrors());

        if (!$gateway->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $gateway->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * Reorder gateways by ids.
     *
     * @param array $ids Array of gateways.
     *
     * @return bool Always true.
     */
    public function reorderGateways(array $ids): bool
    {
        /** @var Gateway[] $allGateways */
        $allGateways = $this->getAllGateways();

        $count = 999;

        // Append those not in the table an put them at 999+
        foreach ($allGateways as $gateway) {
            if ($gateway->isArchived) {
                $ids[$count++] = $gateway->id;
            }
        }

        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()->update('{{%commerce_gateways}}', ['sortOrder' => $sortOrder + 1], ['id' => $id])->execute();
        }

        return true;
    }

    /**
     * Creates a gateway with a given config
     *
     * @param mixed $config The gateway’s class name, or its config, with a `type` value and optionally a `settings` value
     *
     * @return GatewayInterface The gateway
     */
    public function createGateway($config): GatewayInterface
    {
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        // Are they overriding any settings?
        if (!empty($config['handle']) && ($override = $this->getGatewayOverrides($config['handle'])) !== null) {
            // Apply the settings early so the overrides don't get overridden
            ComponentHelper::applySettings($config);
            $config = array_merge($config, $override);
        }

        try {
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
     *
     * @return array|null
     */
    public function getGatewayOverrides(string $handle)
    {
        $gatewaySettings = Plugin::getInstance()->getSettings()->gatewaySettings;

        return $gatewaySettings[$handle] ?? null;
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
                'frontendEnabled',
                'sendCartInfo',
                'isArchived',
                'dateArchived',
                'settings',
            ])
            ->from(['{{%commerce_gateways}}']);
    }
}
