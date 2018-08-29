<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\AddressZoneInterface;
use craft\commerce\events\AddressEvent;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\commerce\records\Address as AddressRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Address service.
 *
 * @property Address $storeLocationAddress the store location address.
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Addresses extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event AddressEvent The event that is raised before an address is saved.
     *
     * Plugins can get notified before an address is being saved
     *
     * ```php
     * use craft\commerce\events\AddressEvent;
     * use craft\commerce\services\Addresses;
     * use yii\base\Event;
     *
     * Event::on(Addresses::class, Addresses::EVENT_BEFORE_SAVE_ADDRESS, function(AddressEvent $e) {
     *     // Do something - perhaps let an external CRM system know about a client's new address
     * });
     * ```
     */
    const EVENT_BEFORE_SAVE_ADDRESS = 'beforeSaveAddress';

    /**
     * @event AddressEvent The event that is raised after an address is saved.
     *
     * Plugins can get notified before an address is being saved
     *
     * ```php
     * use craft\commerce\events\AddressEvent;
     * use craft\commerce\services\Addresses;
     * use yii\base\Event;
     *
     * Event::on(Addresses::class, Addresses::EVENT_AFTER_SAVE_ADDRESS, function(AddressEvent $e) {
     *     // Do something - perhaps set this address as default in an external CRM system
     * });
     * ```
     */
    const EVENT_AFTER_SAVE_ADDRESS = 'afterSaveAddress';

    // Properties
    // =========================================================================

    /**
     * @var Address[]
     */
    private $_addressesById = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns an address by its ID.
     *
     * @param int $addressId the address' ID
     * @return Address|null the matched address or null if not found
     */
    public function getAddressById(int $addressId)
    {
        if (array_key_exists($addressId, $this->_addressesById)) {
            return $this->_addressesById[$addressId];
        }

        $result = $this->_createAddressQuery()
            ->where(['id' => $addressId])
            ->one();

        return $this->_addressesById[$addressId] = $result ? new Address($result) : null;
    }

    /**
     * Returns all of a customer's addresses, by the customer's ID.
     *
     * @param int $customerId the customer's ID
     * @return Address[] an array of matched addresses
     */
    public function getAddressesByCustomerId(int $customerId): array
    {
        $rows = $this->_createAddressQuery()
            ->innerJoin('{{%commerce_customers_addresses}} customerAddresses', '[[customerAddresses.addressId]] = [[addresses.id]]')
            ->where(['customerAddresses.customerId' => $customerId])
            ->all();

        $addresses = [];

        foreach ($rows as $row) {
            $addresses[] = new Address($row);
        }

        return $addresses;
    }

    /**
     * Returns an address by an address id and customer id.
     *
     * @param int $addressId the address id
     * @param int $customerId the customer's ID
     * @return Address|null the matched address or null if not found
     */
    public function getAddressByIdAndCustomerId(int $addressId, $customerId = null)
    {
        $result = $this->_createAddressQuery()
            ->innerJoin('{{%commerce_customers_addresses}} customerAddresses', '[[customerAddresses.addressId]] = [[addresses.id]]')
            ->where(['customerAddresses.customerId' => $customerId])
            ->andWhere(['addresses.id' => $addressId])
            ->one();

        return $this->_addressesById[$addressId] = $result ? new Address($result) : null;
    }

    /**
     * Returns the stock location or a blank address if it's not defined.
     *
     * @return Address
     */
    public function getStoreLocationAddress(): Address
    {
        $result = $this->_createAddressQuery()
            ->where(['isStoreLocation' => true])
            ->one();

        if (!$result) {
            return new Address();
        }

        return new Address($result);
    }

    /**
     * Saves an address.
     *
     * @param Address $addressModel The address to be saved.
     * @param bool $runValidation should we validate this address before saving.
     * @return bool Whether the address was saved successfully.
     * @throws InvalidConfigException if an address does not exist.
     */
    public function saveAddress(Address $addressModel, bool $runValidation = true): bool
    {
        $isNewAddress = !$addressModel->id;
        $plugin = Plugin::getInstance();

        if ($addressModel->id) {
            $addressRecord = AddressRecord::findOne($addressModel->id);

            if (!$addressRecord) {
                throw new InvalidArgumentException('No address exists with the ID “{id}”', ['id' => $addressModel->id]);
            }
        } else {
            $addressRecord = new AddressRecord();
        }

        //Raise the beforeSaveAddress event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_ADDRESS)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_ADDRESS, new AddressEvent([
                'address' => $addressModel,
                'isNew' => $isNewAddress
            ]));
        }

        if ($runValidation && !$addressModel->validate()) {
            Craft::info('Address could not save due to validation error.', __METHOD__);
            return false;
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
        $addressRecord->isStoreLocation = $addressModel->isStoreLocation;
        $addressRecord->stateId = $addressModel->stateId;
        $addressRecord->stateName = $addressModel->stateName;

        if ($addressRecord->isStoreLocation && $addressRecord->id) {
            Craft::$app->getDb()->createCommand()->update('{{%commerce_addresses}}', ['isStoreLocation' => false], 'id <> :thisId', [':thisId' => $addressRecord->id])->execute();
        }

        $addressRecord->save(false);

        if ($isNewAddress) {
            // Now that we have a record ID, save it on the model
            $addressModel->id = $addressRecord->id;
        }

        //Raise the afterSaveAddress event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_ADDRESS)) {
            $this->trigger(self::EVENT_AFTER_SAVE_ADDRESS, new AddressEvent([
                'address' => $addressModel,
                'isNew' => $isNewAddress
            ]));
        }

        return true;
    }

    /**
     * Deletes an address by its ID.
     *
     * @param int $id the address' ID
     * @return bool whether the address was deleted successfully
     */
    public function deleteAddressById(int $id): bool
    {
        $address = AddressRecord::findOne($id);

        if (!$address) {
            return false;
        }

        return (bool)$address->delete();
    }

    /**
     * @param $address
     * @param $zone
     * @return bool
     */
    public function addressWithinZone($address, AddressZoneInterface $zone)
    {
        if ($zone->getIsCountryBased()) {
            $countryIds = $zone->getCountryIds();

            if (in_array($address->countryId, $countryIds, false)) {
                return true;
            }
        } else {
            $states = [];
            $countries = [];
            $stateNames = [];
            $stateAbbr = [];
            /** @var State $state */
            foreach ($zone->getStates() as $state) {
                $states[] = $state->id;
                $countries[] = $state->countryId;
                $stateNames[] = $state->name;
                $stateAbbr[] = $state->abbreviation;
            }

            $countryAndStateMatch = (in_array($address->countryId, $countries, false) && in_array($address->stateId, $states, false));
            $countryAndStateNameMatch = (in_array($address->countryId, $countries, false) && in_array(strtolower($address->getStateText()), array_map('strtolower', $stateNames), false));
            $countryAndStateAbbrMatch = (in_array($address->countryId, $countries, false) && in_array(strtolower($address->getAbbreviationText()), array_map('strtolower', $stateAbbr), false));

            if ($countryAndStateMatch || $countryAndStateNameMatch || $countryAndStateAbbrMatch) {
                return true;
            }
        }

        return false;
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
