<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\AddressZoneInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\AddressEvent;
use craft\commerce\events\PurgeAddressesEvent;
use craft\commerce\models\Address;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\Address as AddressRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use LitEmoji\LitEmoji;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\db\Exception;
use yii\db\Expression;

/**
 * Address service.
 *
 * @property Address $storeLocationAddress the store location address.
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Addresses extends Component
{
    /**
     * @event AddressEvent The event that is triggered before an address is saved.
     *
     * ```php
     * use craft\commerce\events\AddressEvent;
     * use craft\commerce\services\Addresses;
     * use craft\commerce\models\Address;
     * use yii\base\Event;
     *
     * Event::on(
     *     Addresses::class,
     *     Addresses::EVENT_BEFORE_SAVE_ADDRESS,
     *     function(AddressEvent $event) {
     *         // @var Address $address
     *         $address = $event->address;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Update customer’s address in an external CRM
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_SAVE_ADDRESS = 'beforeSaveAddress';

    /**
     * @event AddressEvent The event that is triggered after an address is saved.
     *
     * ```php
     * use craft\commerce\events\AddressEvent;
     * use craft\commerce\services\Addresses;
     * use craft\commerce\models\Address;
     * use yii\base\Event;
     *
     * Event::on(
     *     Addresses::class,
     *     Addresses::EVENT_AFTER_SAVE_ADDRESS,
     *     function(AddressEvent $event) {
     *         // @var Address $address
     *         $address = $event->address;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Set the default address in an external CRM
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_ADDRESS = 'afterSaveAddress';

    /**
     * @event AddressEvent The event that is triggered before an address is deleted.
     *
     * ```php
     * use craft\commerce\events\AddressEvent;
     * use craft\commerce\services\Addresses;
     * use craft\commerce\models\Address;
     * use yii\base\Event;
     *
     * Event::on(
     *     Addresses::class,
     *     Addresses::EVENT_BEFORE_DELETE_ADDRESS,
     *     function(AddressEvent $event) {
     *         // @var Address $address
     *         $address = $event->address;
     *
     *         // Invalidate customer address cache
     *         // ...
     *     }
     * );
     */
    const EVENT_BEFORE_DELETE_ADDRESS = 'beforeDeleteAddress';

    /**
     * @event AddressEvent The event that is triggered after an address is deleted.
     *
     * ```php
     * use craft\commerce\events\AddressEvent;
     * use craft\commerce\services\Addresses;
     * use craft\commerce\models\Address;
     * use yii\base\Event;
     *
     * Event::on(
     *     Addresses::class,
     *     Addresses::EVENT_AFTER_DELETE_ADDRESS,
     *     function(AddressEvent $event) {
     *         // @var Address $address
     *         $address = $event->address;
     *
     *         // Remove this address from a payment gateway
     *         // ...
     *     }
     * );
     */
    const EVENT_AFTER_DELETE_ADDRESS = 'afterDeleteAddress';

    /**
     * @event AddressEvent The event that is triggered before purgeable addresses are deleted.
     *
     * ```php
     * use craft\commerce\events\PurgeAddressesEvent;
     * use craft\commerce\services\Addresses;
     * use yii\base\Event;
     *
     * Event::on(
     *     Addresses::class,
     *     Addresses::EVENT_BEFORE_PURGE_ADDRESSES,
     *     function(PurgeAddressesEvent $event) {
     *         // @var Query $addressQuery
     *         $addressQuery = $event->addressQuery;
     *
     *         // Add an `$addressQuery->andWhere(..)` to change the addresses that will be purged query
     *         // $event->addressQuery = $addressQuery
     *     }
     * );
     */
    const EVENT_BEFORE_PURGE_ADDRESSES = 'beforePurgeAddresses';

    /**
     * @var Address[]
     */
    private $_addressesById = [];

    /**
     * @var Address|null
     */
    private $_storeLocationAddress;


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
            ->innerJoin(Table::CUSTOMERS_ADDRESSES . ' customerAddresses', '[[customerAddresses.addressId]] = [[addresses.id]]')
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
            ->innerJoin(Table::CUSTOMERS_ADDRESSES . ' customerAddresses', '[[customerAddresses.addressId]] = [[addresses.id]]')
            ->where(['customerAddresses.customerId' => $customerId])
            ->andWhere(['addresses.id' => $addressId])
            ->one();

        return $this->_addressesById[$addressId] = $result ? new Address($result) : null;
    }

    /**
     * Returns the store location address, or a blank address if it's not defined.
     *
     * @return Address
     */
    public function getStoreLocationAddress(): Address
    {
        if ($this->_storeLocationAddress !== null) {
            return $this->_storeLocationAddress;
        }

        $result = $this->_createAddressQuery()
            ->where(['isStoreLocation' => true])
            ->one();

        $this->_storeLocationAddress = $result ? new Address($result) : new Address(['isStoreLocation' => true]);

        return $this->_storeLocationAddress;
    }

    /**
     * Saves an address.
     *
     * @param Address $addressModel The address to be saved.
     * @param bool $runValidation should we validate this address before saving.
     * @return bool Whether the address was saved successfully.
     * @throws \InvalidArgumentException if an address does not exist.
     * @throws Exception
     */
    public function saveAddress(Address $addressModel, bool $runValidation = true): bool
    {
        $isNewAddress = !$addressModel->id;

        if ($addressModel->id) {
            $addressRecord = AddressRecord::findOne($addressModel->id);

            if (!$addressRecord) {
                throw new InvalidArgumentException(Craft::t('commerce', 'No address exists with the ID “{id}”', ['id' => $addressModel->id]));
            }
        } else {
            $addressRecord = new AddressRecord();
        }

        //Raise the beforeSaveAddress event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_ADDRESS)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_ADDRESS, new AddressEvent([
                'address' => $addressModel,
                'isNew' => $isNewAddress,
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
        $addressRecord->fullName = $addressModel->fullName;
        $addressRecord->address1 = $addressModel->address1;
        $addressRecord->address2 = $addressModel->address2;
        $addressRecord->address3 = $addressModel->address3;
        $addressRecord->city = $addressModel->city;
        $addressRecord->zipCode = $addressModel->zipCode;
        $addressRecord->phone = $addressModel->phone;
        $addressRecord->alternativePhone = $addressModel->alternativePhone;
        $addressRecord->label = $addressModel->label;
        $addressRecord->notes = LitEmoji::unicodeToShortcode($addressModel->notes);
        $addressRecord->businessName = $addressModel->businessName;
        $addressRecord->businessTaxId = $addressModel->businessTaxId;
        $addressRecord->businessId = $addressModel->businessId;
        $addressRecord->countryId = $addressModel->countryId;
        $addressRecord->isStoreLocation = $addressModel->isStoreLocation;
        $addressRecord->stateId = $addressModel->stateId;
        $addressRecord->stateName = $addressModel->stateName;
        $addressRecord->custom1 = $addressModel->custom1;
        $addressRecord->custom2 = $addressModel->custom2;
        $addressRecord->custom3 = $addressModel->custom3;
        $addressRecord->custom4 = $addressModel->custom4;
        $addressRecord->isEstimated = $addressModel->isEstimated;

        if ($addressRecord->isStoreLocation && $addressRecord->id) {
            Craft::$app->getDb()->createCommand()->update(Table::ADDRESSES, ['isStoreLocation' => false], 'id <> :thisId', [':thisId' => $addressRecord->id])->execute();
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
                'isNew' => $isNewAddress,
            ]));
        }

        // Clear cache
        $this->_storeLocationAddress = null;

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
        $addressRecord = AddressRecord::findOne($id);

        if (!$addressRecord) {
            return false;
        }

        // Get the Address model before deletion to pass to the Event.
        $address = $this->getAddressById($id);

        //Raise the beforeDeleteAddress event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_ADDRESS)) {
            $this->trigger(self::EVENT_BEFORE_DELETE_ADDRESS, new AddressEvent([
                'address' => $address,
                'isNew' => false,
            ]));
        }

        $result = (bool)$addressRecord->delete();

        //Raise the afterDeleteAddress event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_ADDRESS)) {
            $this->trigger(self::EVENT_AFTER_DELETE_ADDRESS, new AddressEvent([
                'address' => $address,
                'isNew' => false,
            ]));
        }

        return $result;
    }

    /**
     * @param Address $address
     * @param $zone
     * @return bool
     */
    public function addressWithinZone($address, AddressZoneInterface $zone): bool
    {
        if ($zone->getIsCountryBased()) {
            $countryIds = $zone->getCountryIds();

            if (!in_array($address->countryId, $countryIds, false)) {
                return false;
            }
        }

        if (!$zone->getIsCountryBased()) {
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

            if (!$countryAndStateMatch && !$countryAndStateNameMatch && !$countryAndStateAbbrMatch) {
                return false;
            }
        }

        // Do we have a condition formula for the zip matching? Blank condition will match all
        if (is_string($zone->getZipCodeConditionFormula()) && $zone->getZipCodeConditionFormula() !== '') {
            $formulasService = Plugin::getInstance()->getFormulas();
            $conditionFormula = $zone->getZipCodeConditionFormula();
            $zipCode = $address->zipCode;

            return (bool)$formulasService->evaluateCondition($conditionFormula, ['zipCode' => $zipCode], 'Zip Code condition formula matching address');
        }

        return true;
    }

    /**
     * Deletes all addresses not related to a customer, cart or order
     *
     * @throws Exception
     * @throws \yii\base\ExitException
     * @since 3.0.4
     */
    public function purgeOrphanedAddresses()
    {
        $select = new Expression('DISTINCT [[addresses.id]] id');
        $addresses = (new Query())
            ->select([$select])
            ->from(Table::ADDRESSES . ' addresses')
            ->leftJoin(Table::ORDERS . ' bo', '[[addresses.id]] = [[bo.billingAddressId]]')
            ->leftJoin(Table::ORDERS . ' beo', '[[addresses.id]] = [[beo.estimatedBillingAddressId]]')
            ->leftJoin(Table::ORDERS . ' so', '[[addresses.id]] = [[so.shippingAddressId]]')
            ->leftJoin(Table::ORDERS . ' seo', '[[addresses.id]] = [[seo.estimatedShippingAddressId]]')
            ->leftJoin(Table::CUSTOMERS_ADDRESSES . ' c', '[[addresses.id]] = [[c.addressId]]')
            ->where([
                'and', [
                    '[[so.shippingAddressId]]' => null,
                    '[[seo.estimatedShippingAddressId]]' => null,
                    '[[c.addressId]]' => null,
                    '[[bo.billingAddressId]]' => null,
                    '[[beo.estimatedBillingAddressId]]' => null,
                    '[[addresses.isStoreLocation]]' => false,
                ],
            ]);

        $event = new PurgeAddressesEvent([
            'addressesQuery' => $addresses,
        ]);

        //Raise the beforePurgeDeleteAddresses event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_PURGE_ADDRESSES)) {
            $this->trigger(self::EVENT_BEFORE_PURGE_ADDRESSES, $event);
        }

        if ($event->isValid) {
            foreach ($event->addressesQuery->batch(500) as $address) {
                $ids = ArrayHelper::getColumn($address, 'id', false);

                if (!empty($ids)) {
                    Craft::$app->getDb()->createCommand()
                        ->delete(Table::ADDRESSES, ['id' => $ids])
                        ->execute();
                }
            }
        }
    }

    /**
     * @param array $address
     * @return array
     * @since 3.1
     */
    public function removeReadOnlyAttributesFromArray(array $address): array
    {
        if (empty($address)) {
            return $address;
        }

        // Remove readonly attributes
        $readOnly = [
            'countryIso',
            'countryText',
            'stateText',
            'abbreviationText',
            'addressLines',
        ];
        foreach ($readOnly as $item) {
            if (array_key_exists($item, $address)) {
                unset($address[$item]);
            }
        }

        return $address;
    }

    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.2.0
     */
    public function eagerLoadAddressesForOrders(array $orders): array
    {
        $shippingAddressIds = array_filter(ArrayHelper::getColumn($orders, 'shippingAddressId'));
        $billingAddressIds = array_filter(ArrayHelper::getColumn($orders, 'billingAddressId'));
        $ids = array_unique(array_merge($shippingAddressIds, $billingAddressIds));

        $addressesData = $this->_createAddressQuery()->andWhere(['id' => $ids])->all();

        $addresses = [];
        foreach ($addressesData as $result) {
            $address = new Address($result);
            $addresses[$address->id] = $address;
        }

        foreach ($orders as $key => $order) {
            if (isset($order['shippingAddressId'], $addresses[$order['shippingAddressId']])) {
                $order->setShippingAddress($addresses[$order['shippingAddressId']]);
            }

            if (isset($order['billingAddressId'], $addresses[$order['billingAddressId']])) {
                $order->setBillingAddress($addresses[$order['billingAddressId']]);
            }

            $orders[$key] = $order;
        }

        return $orders;
    }

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
                'addresses.fullName',
                'addresses.countryId',
                'addresses.stateId',
                'addresses.address1',
                'addresses.address2',
                'addresses.address3',
                'addresses.city',
                'addresses.zipCode',
                'addresses.phone',
                'addresses.alternativePhone',
                'addresses.label',
                'addresses.notes',
                'addresses.businessName',
                'addresses.businessTaxId',
                'addresses.businessId',
                'addresses.stateName',
                'addresses.custom1',
                'addresses.custom2',
                'addresses.custom3',
                'addresses.custom4',
                'addresses.isEstimated',
                'addresses.isStoreLocation',
                'addresses.dateCreated',
                'addresses.dateUpdated',
            ])
            ->from([Table::ADDRESSES . ' addresses']);
    }
}
