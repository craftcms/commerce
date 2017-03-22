<?php
namespace craft\commerce\services;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\models\Country;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\Address as AddressRecord;
use craft\commerce\records\Customer;
use yii\base\Component;
use yii\base\Event;
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
    /**
     * @param int $id
     *
     * @return Address|null
     */
    public function getAddressById(int $id)
    {

        $result = AddressRecord::find()->where(['id' => $id])->one();

        if ($result) {
            return new Address($result);
        }

        return null;
    }

    /**
     * @param int $id Customer ID
     *
     * @return Address[]
     */
    public function getAddressesByCustomerId(int $id)
    {
        /** @var Customer $record */
        $record = Customer::find()->with('addresses')->where(['id' => $id])->all();
        $addresses = $record ? $record->addresses : [];

        return Address::populateModels($addresses);
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
                throw new Exception(Craft::t('commerce', 'commerce', 'No address exists with the ID “{id}”',
                    ['id' => $addressModel->id]));
            }
        } else {
            $addressRecord = new AddressRecord();
        }

        //raising event
        $event = new Event($this, [
            'address' => $addressModel,
            'isNewAddress' => $isNewAddress
        ]);
        $this->onBeforeSaveAddress($event);

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
        if ($country) {
            if ($country->stateRequired && (!$state || ($state && $state->countryId !== $country->id))) {
                $addressModel->addError('stateId', Craft::t('commerce', 'commerce', 'commerce', 'Country requires a related state selected.'));
            }
        }

        if ($validate) {
            $addressRecord->validate();
            $addressModel->addErrors($addressRecord->getErrors());
        }

        if (!$addressModel->hasErrors() && $event->performAction) {

            $addressRecord->save(false);

            if ($isNewAddress) {
                // Now that we have a record ID, save it on the model
                $addressModel->id = $addressRecord->id;
            }

            //raising event
            $event = new Event($this, [
                'address' => $addressModel
            ]);
            $this->onSaveAddress($event);

            return true;
        } else {
            return false;
        }
    }

    public function onBeforeSaveAddress(Event $event)
    {
        $params = $event->params;
        if (empty($params['address']) || !($params['address'] instanceof Address)) {
            throw new Exception('onBeforeSaveAddress event requires "address" param with Commerce_AddressModel instance');
        }
        $this->raiseEvent('onBeforeSaveAddress', $event);
    }

    public function onSaveAddress(Event $event)
    {
        $params = $event->params;
        if (empty($params['address']) || !($params['address'] instanceof Address)) {
            throw new Exception('onSaveAddress event requires "address" param with Commerce_AddressModel instance');
        }
        $this->raiseEvent('onSaveAddress', $event);
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

}
