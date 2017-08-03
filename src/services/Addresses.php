<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\events\AddressEvent;
use craft\commerce\models\Address;
use craft\commerce\models\Country;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\Address as AddressRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Address service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Addresses extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event AddressEvent The event that is raised before an address is saved.
     */
    const EVENT_BEFORE_SAVE_ADDRESS = 'beforeSaveAddress';

    /**
     * @event AddressEvent The event that is raised after an address is saved.
     */
    const EVENT_AFTER_SAVE_ADDRESS = 'afterSaveAddress';

    // Properties
    // =========================================================================

    /**
     * @var Address[]
     */
    private $_addressesById = [];

    /**
     * @var Address[][]
     */
    private $_addressesByCustomerId = [];

    // Public Methods
    // =========================================================================

    /**
     * Return an address by it's id.
     * 
     * @param int $addressId The address id.
     *
     * @return Address|null The matched address or null if not found.
     */
    public function getAddressById(int $addressId)
    {
        if (!isset($this->_addressesById[$addressId])) {
            $row = $this->_createAddressQuery()
                ->where(['id' => $addressId])
                ->one();

            $this->_addressesById[$addressId] = $row ? new Address($row) : null;
        }

        return $this->_addressesById[$addressId];
    }

    /**
     * Get an array of addresses by a customer id.
     *
     * @param int $customerId Customer ID
     *
     * @return Address[] An array of matched addresses.
     */
    public function getAddressesByCustomerId(int $customerId): array
    {
        if (!isset($this->_addressesByCustomerId[$customerId])) {
            $rows = $this->_createAddressQuery()
                ->innerJoin('{{%commerce_customers_addresses}} customerAddresses', '[[customerAddresses.addressId]] = [[addresses.id]]')
                ->where(['customerAddresses.customerId' => $customerId])
                ->all();

            $this->_addressesByCustomerId[$customerId] = [];

            foreach ($rows as $row) {
                $this->_addressesByCustomerId[$customerId][] = new Address($row);
            }
        }

        return $this->_addressesByCustomerId[$customerId];
    }

    /**
     * Save an address.
     * 
     * @param Address $addressModel The address to be saved.
     * @param bool    $validate should we validate this address before saving.
     *
     * @return bool Whether the address was saved successfully.
     * @throws Exception if an address does not exist.
     */
    public function saveAddress(Address $addressModel, bool $validate = true): bool
    {

        $isNewAddress = !$addressModel->id;

        if ($addressModel->id) {
            $addressRecord = AddressRecord::findOne($addressModel->id);

            if (!$addressRecord) {
                throw new Exception(Craft::t('commerce', 'No address exists with the ID “{id}”',
                    ['id' => $addressModel->id]));
            }
        } else {
            $addressRecord = new AddressRecord();
        }

        //Raise the beforeSaveAddress event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_ADDRESS)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_ADDRESS, new AddressEvent($this, [
                'address' => $addressModel,
                'isNewAddress' => $isNewAddress
            ]));
        }

        $addressRecord->attention = $addressModel->attention;
        $addressRecord->title = $addressModel->title;
        $addressRecord->firstName = $addressModel->firstName;
        $addressRecord->lastName = $addressModel->lastName;
        $addressRecord->address1 = $addressModel->address1;
        $addressRecord->address2 = $addressModel->address2;
        $addressRecord->city = $addressModel->city;
        $addressRecord->zipCode = $addressModel->zipCode;
        $addressRecord->phone = $addressModel->phone;
        $addressRecord->alternativePhone = $addressModel->alternativePhone;
        $addressRecord->businessName = $addressModel->businessName;
        $addressRecord->businessTaxId = $addressModel->businessTaxId;
        $addressRecord->businessId = $addressModel->businessId;
        $addressRecord->countryId = $addressModel->countryId;

        $plugin = Plugin::getInstance();

        if (!empty($addressModel->stateValue)) {
            if ($plugin->getStates()->getStateById($addressModel->stateValue)) {
                $addressRecord->stateId = $addressModel->stateId = $addressModel->stateValue;
                $addressRecord->stateName = null;
                $addressModel->stateName = null;
            } else {
                $addressRecord->stateId = null;
                $addressModel->stateId = null;
                $addressRecord->stateName = $addressModel->stateName = $addressModel->stateValue;
            }
        } else {
            $addressRecord->stateId = $addressModel->stateId;
            $addressRecord->stateName = $addressModel->stateName;
        }

        /** @var Country $state */
        $country = $addressRecord->countryId ? $plugin->getCountries()->getCountryById($addressRecord->countryId) : null;
        /** @var State $state */
        $state = $addressRecord->stateId ? $plugin->getStates()->getStateById($addressRecord->stateId) : null;

        // Check country’s stateRequired option
        if ($country && $country->stateRequired && (!$state || ($state && $state->countryId !== $country->id))) {
            $addressModel->addError('stateId', Craft::t('commerce', 'Country requires a related state selected.'));
        }

        if ($validate) {
            $addressRecord->validate();
            $addressModel->addErrors($addressRecord->getErrors());
        }

        if (!$addressModel->hasErrors()) {

            $addressRecord->save(false);

            if ($isNewAddress) {
                // Now that we have a record ID, save it on the model
                $addressModel->id = $addressRecord->id;
            }

            //Raise the afterSaveAddress event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_ADDRESS)) {
                $this->trigger(self::EVENT_AFTER_SAVE_ADDRESS, new AddressEvent($this, [
                    'address' => $addressModel,
                    'isNewAddress' => $isNewAddress
                ]));
            }

            return true;
        }

        return false;
    }

    /**
     * Delete an address by it's id.
     *
     * @param int $id The id.
     *
     * @return bool Whether the address was deleted successfully.
     */
    public function deleteAddressById(int $id): bool
    {
        $address = AddressRecord::findOne($id);

        if (!$address) {
            return false;
        }

        return (bool)$address->delete();
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a Query object prepped for retrieving addresses.
     *
     * @return Query The query object.
     */
    private function _createAddressQuery(): Query
    {
        return (new Query())
            ->select([
                'addresses.id',
                'addresses.attention',
                'addresses.title',
                'addresses.firstName',
                'addresses.lastName',
                'addresses.countryId',
                'addresses.stateId',
                'addresses.address1',
                'addresses.address2',
                'addresses.city',
                'addresses.zipCode',
                'addresses.phone',
                'addresses.alternativePhone',
                'addresses.businessName',
                'addresses.businessTaxId',
                'addresses.businessId',
                'addresses.stateName'
            ])
            ->from(['{{%commerce_addresses}} addresses']);
    }
}
