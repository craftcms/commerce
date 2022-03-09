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
use yii\base\Component;

/**
 * Stores service. This manages the store level settings.
 *
 * @property-read Address $storeLocationAddress
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
     * @var ?Address
     */
    private ?Address $_storeLocationAddress = null;

    public function init()
    {
        parent::init();

        // Always ensure we have a store record.
        if ($this->_store == null) {
            $store = $this->_createStoreQuery()->one();

            if ($store === null) {
                $storeRecord = new StoreRecord();
                $storeRecord->save();
                $this->_store = new StoreModel();
                $this->_store->id = $storeRecord->id;
            }else{
                $this->_store = new StoreModel();
                $this->_store->setAttributes($store, false);
            }

            $this->_store->locationAddressId = $this->getStoreLocationAddress()->id; // ensure it is created if not.
        }

        return true;
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
     * Returns the store location address
     *
     * @return Address
     */
    public function getStoreLocationAddress(): Address
    {
        if ($this->_storeLocationAddress !== null) {
            return $this->_storeLocationAddress;
        }

        $this->_storeLocationAddress = AddressElement::findOne($this->_store->locationAddressId);

        if ($this->_storeLocationAddress === null) {
            $this->_storeLocationAddress = new AddressElement();
            $this->_storeLocationAddress->title = 'Store';
            Craft::$app->getElements()->saveElement($this->_storeLocationAddress, false);
        }

        return $this->_storeLocationAddress;
    }

    /**
     * @return array|string[]
     */
    public function getAllEnabledCountriesAsList()
    {
        $enabledCountries = $this->_store->countries;
        // TODO merge in the custom countries and filter out the disabled countries
        return Craft::$app->getAddresses()->getCountryRepository()->getList(Craft::$app->language);
    }

    /**
     * @return array|string[]
     */
    public function getAllEnabledAdministrativeAreasAsList($countryCode)
    {
        $enabledAdministrativeAreas = $this->_store->administrativeAreas;
        // TODO merge in the custom states and filter out the disabled states
        $countries = Craft::$app->getAddresses()->getCountryRepository()->getList(Craft::$app->language);
        return Craft::$app->getAddresses()->getSubdivisionRepository()->getList([$countryCode], Craft::$app->language);
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
                'enabledCountries',
                'enabledAdministrativeAreas',
            ])
            ->from([Table::STORES]);
    }
}
