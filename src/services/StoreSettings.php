<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\StoreSettings as StoreSettingsModel;
use craft\commerce\Plugin;
use craft\commerce\records\StoreSettings as StoreSettingsRecord;
use craft\db\Query;
use craft\elements\Address;
use craft\events\AuthorizationCheckEvent;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Store Settings service.
 *
 * @property-read StoreSettingsModel $store
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class StoreSettings extends Component
{
    /**
     * @var Collection<StoreSettingsModel>|null
     */
    private ?Collection $_allStoreSettings = null;

    /**
     * Returns the store record.
     *
     * @param int $id
     * @return StoreSettingsModel
     */
    public function getStoreSettingsById(int $id): StoreSettingsModel
    {
        $store = Plugin::getInstance()->getStores()->getStoreById($id);

        if (!$store) {
            throw new InvalidConfigException('Store not found');
        }

        $storeSettings = $this->getAllStoreSettings()->firstWhere('id', $id);

        if (!$storeSettings) {
            $storeSettingsRecord = new StoreSettingsRecord();
            $storeSettingsRecord->id = $id;

            /** @var StoreSettingsModel $storeSettings */
            $storeSettings = Craft::createObject([
                'class' => StoreSettingsModel::class,
                'id' => $storeSettingsRecord->id,
            ]);

            // Create a new blank store location
            $locationAddress = $storeSettings->getLocationAddress();
            $storeSettingsRecord->locationAddressId = $locationAddress->id;

            $storeSettingsRecord->save();


            $this->getAllStoreSettings()->put($storeSettings->id, $storeSettings);
        }

        return $storeSettings;
    }

    /**
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getAllStoreSettings(): Collection
    {
        if ($this->_allStoreSettings === null) {
            $this->_allStoreSettings = collect();
            $storeSettings = $this->_createStoreSettingsQuery()->all();

            foreach ($storeSettings as $storeSetting) {
                $this->_allStoreSettings->put($storeSetting['id'], Craft::createObject([
                    'class' => StoreSettingsModel::class,
                    'attributes' => $storeSetting,
                ]));
            }
        }

        return $this->_allStoreSettings ?? collect();
    }

    /**
     * Saves the store
     *
     * @param StoreSettingsModel $storeSettings
     * @return bool
     * @throws InvalidConfigException
     */
    public function saveStoreSettings(StoreSettingsModel $storeSettings): bool
    {
        $storeSettingsRecord = StoreSettingsRecord::findOne($storeSettings->id);

        if (!$storeSettingsRecord) {
            throw new InvalidConfigException('Invalid store ID');
        }

        $storeSettingsRecord->countries = $storeSettings->countries;
        $storeSettingsRecord->marketAddressCondition = $storeSettings->marketAddressCondition->getConfig();

        if (!$storeSettingsRecord->save()) {
            return false;
        }

        $this->getAllStoreSettings()->put($storeSettings->id, $storeSettings);
        return true;
    }

    /**
     * @param AuthorizationCheckEvent $event
     * @return void
     */
    public function authorizeStoreLocationView(AuthorizationCheckEvent $event): void
    {
        if (!$storeSettingsRecord = $this->_checkStoreLocationAuthorization($event)) {
            return;
        }

        // @TODO check store permissions using return from `$storeSettingsRecord`
        $event->authorized = true;
    }

    /**
     * @param AuthorizationCheckEvent $event
     * @return void
     */
    public function authorizeStoreLocationEdit(AuthorizationCheckEvent $event): void
    {
        if (!$storeSettingsRecord = $this->_checkStoreLocationAuthorization($event)) {
            return;
        }

        // @TODO check store permissions using return from `$storeSettingsRecord`
        $event->authorized = true;
    }

    /**
     * @param AuthorizationCheckEvent $event
     * @return StoreSettingsRecord|false
     */
    private function _checkStoreLocationAuthorization(AuthorizationCheckEvent $event): StoreSettingsRecord|false
    {
        if (!$event->element instanceof Address) {
            return false;
        }

        $storeSettings = StoreSettingsRecord::findOne(['locationAddressId' => $event->element->getCanonicalId()]);
        if (!$storeSettings) {
            return false;
        }

        return $storeSettings;
    }

    /**
     * Returns a Query object prepped for retrieving the store.
     */
    private function _createStoreSettingsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'marketAddressCondition',
                'locationAddressId',
                'countries',
            ])
            ->from([Table::STORESETTINGS]);
    }
}
