<?php
namespace Craft;

/**
 * Address service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_AddressesService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Commerce_AddressModel
	 */
	public function getAddressById ($id)
	{
		$record = Commerce_AddressRecord::model()->findById($id);

		return Commerce_AddressModel::populateModel($record);
	}

	/**
	 * @param $id
	 *
	 * @return array
	 */
	public function getAddressesByCustomerId ($id)
	{
		$records = Commerce_AddressRecord::model()->with('country',
			'state')->findAllByAttributes(['customerId' => $id]);

		return Commerce_AddressModel::populateModels($records);
	}

	/**
	 * @param Commerce_AddressModel $addressModel
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function saveAddress (Commerce_AddressModel $addressModel)
	{
		if ($addressModel->id)
		{
			$addressRecord = Commerce_AddressRecord::model()->findById($addressModel->id);

			if (!$addressRecord)
			{
				throw new Exception(Craft::t('No address exists with the ID “{id}”',
					['id' => $addressModel->id]));
			}
		}
		else
		{
			$addressRecord = new Commerce_AddressRecord();
		}

		$addressRecord->firstName = $addressModel->firstName;
		$addressRecord->lastName = $addressModel->lastName;
		$addressRecord->address1 = $addressModel->address1;
		$addressRecord->address2 = $addressModel->address2;
		$addressRecord->city = $addressModel->city;
		$addressRecord->zipCode = $addressModel->zipCode;
		$addressRecord->phone = $addressModel->phone;
		$addressRecord->alternativePhone = $addressModel->alternativePhone;
		$addressRecord->company = $addressModel->company;
		$addressRecord->countryId = $addressModel->countryId;
		$addressRecord->customerId = $addressModel->customerId;

		if (!empty($addressModel->stateValue))
		{
			if (is_numeric($addressModel->stateValue))
			{
				$addressRecord->stateId = $addressModel->stateId = $addressModel->stateValue;
			}
			else
			{
				$addressRecord->stateName = $addressModel->stateName = $addressModel->stateValue;
			}
		}
		else
		{
			$addressRecord->stateId = $addressModel->stateId;
			$addressRecord->stateName = $addressModel->stateName;
		}

		$addressRecord->validate();
		$addressModel->addErrors($addressRecord->getErrors());

		if (!$addressModel->hasErrors())
		{
			// Save it!
			$addressRecord->save(false);

			// Now that we have a record ID, save it on the model
			$addressModel->id = $addressRecord->id;

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param $id
	 *
	 * @return bool
	 */
	public function deleteAddressById ($id)
	{
		return (bool)Commerce_AddressRecord::model()->deleteByPk($id);
	}
}