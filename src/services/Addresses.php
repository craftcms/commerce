<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\events\AddressEvent;
use craft\commerce\models\Address;
use craft\commerce\models\Country;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\Address as AddressRecord;
use craft\commerce\records\Customer;
use craft\db\Query;
use craft\helpers\ArrayHelper;
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

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
     * @return Address|null
     */
    public function getAddressById(int $id)
    {
        $result = AddressRecord::findOne($id);

        if ($result) {
            return $this->_createAddressFromAddressRecord($result);
        }

        return null;
    }

    /**
     * @param int $id Customer ID
     *
     * @return Address[]
     */
    public function getAddressesByCustomerId(int $id): array
    {
        /** @var Customer $record */
        $record = Customer::find()->with('addresses')->where(['id' => $id])->all();
        $addresses = $record ? $record->getAddresses() : [];

        return ArrayHelper::map($addresses, 'id', function($item) {
            return $this->_createAddressFromAddressRecord($item);
        });
    }

    /**
     * @param Address $addressModel
     * @param bool    $validate should we validate this address before saving.
     *
     * @return bool
     * @throws Exception
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

        //raising event
        $event = new AddressEvent($this, [
            'address' => $addressModel,
            'isNewAddress' => $isNewAddress
        ]);
        $this->trigger(self::EVENT_BEFORE_SAVE_ADDRESS, $event);

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

        if (!empty($addressModel->stateValue)) {
            if (Plugin::getInstance()->getStates()->getStateById($addressModel->stateValue)) {
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
        $country = Plugin::getInstance()->getCountries()->getCountryById($addressRecord->countryId);
        /** @var State $state */
        $state = Plugin::getInstance()->getStates()->getStateById($addressRecord->stateId);

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

            //raising event
            $event = new AddressEvent($this, [
                'address' => $addressModel,
                'isNewAddress' => $isNewAddress
            ]);
            $this->trigger(self::EVENT_AFTER_SAVE_ADDRESS, $event);

            return true;
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function deleteAddressById(int $id): bool
    {
        $address = AddressRecord::findOne(['id' => $id]);
        if (!$address) {
            return false;
        }

        return (bool)$address->delete();
    }

    // Private Methods
    // =========================================================================

    /**
     * Creates a Address with attributes from a AddressRecord.
     *
     * @param AddressRecord|null $record
     *
     * @return Address|null
     */
    private function _createAddressFromAddressRecord(AddressRecord $record = null)
    {
        if (!$record) {
            return null;
        }

        return new Address($record->toArray([
            'id',
            'attention',
            'title',
            'firstName',
            'lastName',
            'countryId',
            'stateId',
            'address1',
            'address2',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessName',
            'businessTaxId',
            'businessId',
            'stateName'
        ]));
    }
}
