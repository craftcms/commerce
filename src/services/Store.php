<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\Store as StoreModel;
use craft\commerce\records\Store as StoreRecord;
use craft\db\Query;
use craft\elements\Address;
use craft\elements\Address as AddressElement;
use craft\errors\ElementNotFoundException;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Stores service.
 *
 * @property-read StoreModel $store
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Store extends Component
{
    /**
     * @var ?StoreModel
     */
    private ?StoreModel $_store = null;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        if ($this->_store == null) {
            // We always ensure we have a store record and an associated store address.
            $store = $this->_createStoreQuery()->one(); // get first row only. Only one store at the moment.
            if ($store === null) {
                $storeRecord = new StoreRecord();
                $storeRecord->save();
                $this->_store = new StoreModel(['id' => $storeRecord->id]);
            } else {
                $this->_store = new StoreModel();
                $locationAddressId = $store['locationAddressId'] ?? null;
                if ($locationAddressId === null) {
                    unset($store['locationAddressId']);
                }
                $this->_store->setAttributes($store);
            }

            // Make sure the store always has an address location set.
            $storeLocationAddress = $this->_store->getLocationAddressId() ? AddressElement::findOne($this->_store->locationAddressId) : null;
            if ($storeLocationAddress === null) {
                $this->_createDefaultStoreLocationAddress();
            }
        }
    }

    /**
     * Returns the store record.
     *
     * @return StoreModel
     */
    public function getStore(): StoreModel
    {
        return $this->_store;
    }

    /**
     * Saves the store
     *
     * @param StoreModel $store
     * @return bool
     * @throws InvalidConfigException
     */
    public function saveStore(StoreModel $store): bool
    {
        $storeRecord = StoreRecord::findOne($store->id);

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
     * @return void
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    private function _createDefaultStoreLocationAddress(): void
    {
        if (!$this->_store instanceof StoreModel) {
            return;
        }

        $storeLocationAddress = new AddressElement();
        $storeLocationAddress->title = 'Store';
        $storeLocationAddress->countryCode = 'US';
        if (Craft::$app->getElements()->saveElement($storeLocationAddress, false)) {
            $storeRecord = StoreRecord::findOne($this->_store->id);
            if ($storeRecord === null) {
                return;
            }

            $storeRecord->locationAddressId = $storeLocationAddress->id;
            $storeRecord->save();
            $this->_store->setLocationAddressId($storeLocationAddress->id); // update the model
        }
    }

    /**
     * Returns a Query object prepped for retrieving the store.
     */
    private function _createStoreQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'locationAddressId',
                'marketAddressCondition',
                'countries',
            ])
            ->from([Table::STORES]);
    }
}
