<?php
namespace Craft;

/**
 * Class Market_AddressService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_AddressService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Market_AddressModel
     */
    public function getAddressById($id)
    {
        $record = Market_AddressRecord::model()->findById($id);

        return Market_AddressModel::populateModel($record);
    }

    /**
     * @param $id
     * @return array
     */
    public function getAddressesByCustomerId($id)
    {
        $records = Market_AddressRecord::model()->with('country',
            'state')->findAllByAttributes(['customerId'=>$id]);

        return Market_AddressModel::populateModels($records);
    }

    /**
     * @param Market_AddressModel $addressModel
     *
     * @return bool
     * @throws Exception
     */
    public function saveAddress(Market_AddressModel $addressModel)
    {
        if ($addressModel->id) {
            $addressRecord = Market_AddressRecord::model()->findById($addressModel->id);

            if (!$addressRecord) {
                throw new Exception(Craft::t('No address exists with the ID “{id}”',
                    ['id' => $addressModel->id]));
            }
        } else {
            $addressRecord = new Market_AddressRecord();
        }

        $addressRecord->firstName        = $addressModel->firstName;
        $addressRecord->lastName         = $addressModel->lastName;
        $addressRecord->address1         = $addressModel->address1;
        $addressRecord->address2         = $addressModel->address2;
        $addressRecord->city             = $addressModel->city;
        $addressRecord->zipCode          = $addressModel->zipCode;
        $addressRecord->phone            = $addressModel->phone;
        $addressRecord->alternativePhone = $addressModel->alternativePhone;
        $addressRecord->company          = $addressModel->company;
        $addressRecord->countryId        = $addressModel->countryId;
        $addressRecord->customerId       = $addressModel->customerId;

        if (!empty($addressModel->stateValue)) {
            if (is_numeric($addressModel->stateValue)) {
                $addressRecord->stateId = $addressModel->stateId = $addressModel->stateValue;
            } else {
                $addressRecord->stateName = $addressModel->stateName = $addressModel->stateValue;
            }
        } else {
            $addressRecord->stateId   = $addressModel->stateId;
            $addressRecord->stateName = $addressModel->stateName;
        }

        $addressRecord->validate();
        $addressModel->addErrors($addressRecord->getErrors());

        if (!$addressModel->hasErrors()) {
            // Save it!
            $addressRecord->save(false);

            // Now that we have a record ID, save it on the model
            $addressModel->id = $addressRecord->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     *
     */
    public function deleteAddressById($id)
    {
        Market_AddressRecord::model()->deleteByPk($id);
    }
}