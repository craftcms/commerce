<?php
namespace Craft;

/**
 * Class Market_CustomerService
 *
 * @package Craft
 */
class Market_CustomerService extends BaseApplicationComponent
{
	const SESSION_CUSTOMER = 'market_customer_cookie';

	/** @var Market_CustomerModel */
	private $customer = NULL;

	/**
	 * @param \CDbCriteria|array $criteria
	 * @return Market_CustomerModel[]
	 */
	public function getAll($criteria = [])
	{
		$records = Market_CustomerRecord::model()->findAll($criteria);
		return Market_CustomerModel::populateModels($records);
	}

	/**
	 * @param int $id
	 * @return Market_CustomerModel
	 */
	public function getById($id)
	{
		$record = Market_CustomerRecord::model()->findById($id);
		return Market_CustomerModel::populateModel($record);
	}

	/**
	 * @return bool
	 */
	public function isSaved()
	{
		return !!$this->getCustomer()->id;
	}

	/**
	 * @return Market_CustomerModel
	 */
	public function getCustomer()
	{
		if ($this->customer === NULL) {
			$user = craft()->userSession->getUser();

			if ($user) {
				$record = Market_CustomerRecord::model()->findByAttributes(['userId' => $user->id]);
			} else {
				$id = craft()->session->get(self::SESSION_CUSTOMER);
				if ($id) {
					$record = Market_CustomerRecord::model()->findById($id);
				}
			}

			if (empty($record)) {
				$record = new Market_CustomerRecord;

				if ($user) {
					$record->userId = $user->id;
					$record->email  = $user->email;
				}
			}

			$this->customer = Market_CustomerModel::populateModel($record);
		}

		return $this->customer;
	}

	/**
	 * @param Market_AddressModel $address
	 *
	 * @throws Exception
	 */
	public function saveAddress(Market_AddressModel $address)
	{
		$customer = $this->getSavedCustomer();
		$attr     = [
			'customerId' => $customer->id,
			'addressId'  => $address->id,
		];

		$relation = Market_CustomerAddressRecord::model()->findByAttributes($attr);

		if (!$relation) {
			$relation             = new Market_CustomerAddressRecord;
			$relation->attributes = $attr;
			if (!$relation->save()) {
				$errorsAll = call_user_func_array('array_merge', $relation->getErrors());
				throw new Exception('Could not create customer-record relation: ' . implode('; ', $errorsAll));
			}
		}
	}

	/**
	 * @return Market_CustomerModel
	 * @throws Exception
	 */
	private function getSavedCustomer()
	{
		$customer = $this->getCustomer();
		if (!$customer->id) {
			if($this->save($customer)) {
				craft()->session->add(self::SESSION_CUSTOMER, $customer->id);
			} else {
				$errors = implode(', ', $customer->getAllErrors());
				throw new Exception('Error saving customer: ' . $errors);
			}
		}


		return $customer;
	}

	/**
	 * @param Market_CustomerModel $customer
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function save(Market_CustomerModel $customer)
	{
		if (!$customer->id) {
			$customerRecord = new Market_CustomerRecord();
		} else {
			$customerRecord = Market_CustomerRecord::model()->findById($customer->id);

			if (!$customerRecord) {
				throw new Exception(Craft::t('No customer exists with the ID “{id}”', ['id' => $customer->id]));
			}
		}

		$customerRecord->email  = $customer->email;
		$customerRecord->userId = $customer->userId;

		$customerRecord->validate();
		$customer->addErrors($customerRecord->getErrors());

		if (!$customer->hasErrors()) {
			$customerRecord->save(false);
			$customer->id = $customerRecord->id;

			return true;
		}

		return false;
	}
}