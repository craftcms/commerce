<?php
namespace Craft;

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
class Commerce_AddressesService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_AddressModel|null
     */
    public function getAddressById($id)
    {
        $result = Commerce_AddressRecord::model()->findById($id);

        if ($result) {
            return Commerce_AddressModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param $id
     *
     * @return array
     */
    public function getAddressesByCustomerId($id)
    {
        $record = Commerce_CustomerRecord::model()->with('addresses')->findByAttributes(['id' => $id]);
        $addresses = $record ? $record->addresses : [];
        return Commerce_AddressModel::populateModels($addresses);
    }

    /**
     * @param Commerce_AddressModel $addressModel
     * @param bool $validate should we validate this address before saving.
     *
     * @return bool
     * @throws Exception
     */
    public function saveAddress(Commerce_AddressModel $addressModel, $validate = true)
    {

        $isNewAddress = !$addressModel->id;

        if ($addressModel->id) {
            $addressRecord = Commerce_AddressRecord::model()->findById($addressModel->id);

            if (!$addressRecord) {
                throw new Exception(Craft::t('No address exists with the ID “{id}”',
                    ['id' => $addressModel->id]));
            }
        } else {
            $addressRecord = new Commerce_AddressRecord();
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
            if (craft()->commerce_states->getStateById($addressModel->stateValue)) {
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

        /** @var Commerce_CountryModel $state */
        $country = craft()->commerce_countries->getCountryById($addressRecord->countryId);
        /** @var Commerce_StateModel $state */
        $state = craft()->commerce_states->getStateById($addressRecord->stateId);

        // Check country’s stateRequired option
        if($country)
        {
            if ($country->stateRequired && (!$state || ($state && $state->countryId !== $country->id)))
            {
                $addressModel->addError('stateId', Craft::t('Country requires a related state selected.'));
            }
        }

        if ($validate)
        {
            $addressRecord->validate();
            $addressModel->addErrors($addressRecord->getErrors());
        }

        if (!$addressModel->hasErrors() && $event->performAction) {

            $addressRecord->save(false);

            if($isNewAddress){
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

    /**
     * @param $id
     *
     * @return bool
     */
    public function deleteAddressById($id)
    {
        return (bool)Commerce_AddressRecord::model()->deleteByPk($id);
    }

    /**
     * Event: before saving and address
     * Event params: address(Commerce_AddressModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeSaveAddress(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['address']) || !($params['address'] instanceof Commerce_AddressModel)) {
            throw new Exception('onBeforeSaveAddress event requires "address" param with Commerce_AddressModel instance');
        }
        $this->raiseEvent('onBeforeSaveAddress', $event);
    }

    /**
     * Event: after saving an address.
     * Event params: address(Commerce_AddressModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSaveAddress(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['address']) || !($params['address'] instanceof Commerce_AddressModel)) {
            throw new Exception('onSaveAddress event requires "address" param with Commerce_AddressModel instance');
        }
        $this->raiseEvent('onSaveAddress', $event);
    }

}
