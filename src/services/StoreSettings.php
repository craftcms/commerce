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
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Store Settings service.
 *
 * @property-read StoreSettingsModel $store
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class StoreSettings extends Component
{
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

        $storeSettingsResults = $this->_createStoreSettingsQuery()->where(['id' => $id])->one();

        if (!$storeSettingsResults) {
            $storeSettingsRecord = new StoreSettingsRecord();
            $storeSettings = new StoreSettingsModel();
            $storeSettingsRecord->id = $id;
            $storeSettingsRecord->save();
            $storeSettings->id = $storeSettingsRecord->id;
        } else {
            $storeSettings = Craft::createObject([
                'class' => StoreSettingsModel::class,
                'attributes' => $storeSettingsResults,
            ]);
        }

        return $storeSettings;
    }

    /**
     * Saves the store
     *
     * @param StoreSettingsModel $store
     * @return bool
     * @throws InvalidConfigException
     */
    public function saveStore(StoreSettingsModel $store): bool
    {
        $storeRecord = StoreSettingsRecord::findOne($store->id);

        if (!$storeRecord) {
            throw new InvalidConfigException('Invalid store ID');
        }

        $storeRecord->countries = $store->countries;
        $storeRecord->marketAddressCondition = $store->marketAddressCondition->getConfig();
        $storeRecord->locationAddressId = $store->getLocationAddressId();

        if (!$storeRecord->save()) {
            return false;
        }

        return true;
    }

    /**
     * Returns a Query object prepped for retrieving the store.
     */
    private function _createStoreSettingsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'locationAddressId',
                'marketAddressCondition',
                'countries',
            ])
            ->from([Table::STORESETTINGS]);
    }
}
