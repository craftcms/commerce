<?php
namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_CustomerService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_CustomerService extends BaseApplicationComponent
{
	const SESSION_CUSTOMER = 'market_customer_cookie';

	/** @var Market_CustomerModel */
	private $customer = null;


	/**
	 * Id of current customer record. Guaranteed not null
	 *
	 * @return int
	 * @throws Exception
	 */
	public function getCustomerId ()
	{
		return $this->getSavedCustomer()->id;
	}

	/**
	 * @return Market_CustomerModel
	 * @throws Exception
	 */
	private function getSavedCustomer ()
	{
		$customer = $this->getCustomer();
		if (!$customer->id)
		{
			if ($this->save($customer))
			{
				craft()->session->add(self::SESSION_CUSTOMER, $customer->id);
			}
			else
			{
				$errors = implode(', ', $customer->getAllErrors());
				throw new Exception('Error saving customer: '.$errors);
			}
		}

		return $customer;
	}

	/**
	 * @return Market_CustomerModel
	 */
	public function getCustomer ()
	{
		if ($this->customer === null)
		{
			$user = craft()->userSession->getUser();

			if ($user)
			{
				$record = Market_CustomerRecord::model()->findByAttributes(['userId' => $user->id]);
			}
			else
			{
				$id = craft()->session->get(self::SESSION_CUSTOMER);
				if ($id)
				{
					$record = Market_CustomerRecord::model()->findById($id);
					// If there is a customer record but it is associated with a real user, don't use it when guest.
					if ($record && $record->userId)
					{
						$record = false;
					}
				}
			}

			if (empty($record))
			{
				$record = new Market_CustomerRecord;

				if ($user)
				{
					$record->userId = $user->id;
					$record->email = $user->email;
				}
			}

			$this->customer = Market_CustomerModel::populateModel($record);
		}

		return $this->customer;
	}

	/**
	 * @param Market_CustomerModel $customer
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function save (Market_CustomerModel $customer)
	{
		if (!$customer->id)
		{
			$customerRecord = new Market_CustomerRecord();
		}
		else
		{
			$customerRecord = Market_CustomerRecord::model()->findById($customer->id);

			if (!$customerRecord)
			{
				throw new Exception(Craft::t('No customer exists with the ID “{id}”',
					['id' => $customer->id]));
			}
		}

		$customerRecord->email = $customer->email;
		$customerRecord->userId = $customer->userId;
		$customerRecord->lastUsedBillingAddressId = $customer->lastUsedBillingAddressId;
		$customerRecord->lastUsedShippingAddressId = $customer->lastUsedShippingAddressId;

		$customerRecord->validate();
		$customer->addErrors($customerRecord->getErrors());

		if (!$customer->hasErrors())
		{
			$customerRecord->save(false);
			$customer->id = $customerRecord->id;

			return true;
		}

		return false;
	}

	/**
	 * @param \CDbCriteria|array $criteria
	 *
	 * @return Market_CustomerModel[]
	 */
	public function getAll ($criteria = [])
	{
		$records = Market_CustomerRecord::model()->findAll($criteria);

		return Market_CustomerModel::populateModels($records);
	}

	/**
	 * @param int $id
	 *
	 * @return Market_CustomerModel
	 */
	public function getById ($id)
	{
		$record = Market_CustomerRecord::model()->findById($id);

		return Market_CustomerModel::populateModel($record);
	}

	/**
	 * @return bool
	 */
	public function isSaved ()
	{
		return !!$this->getCustomer()->id;
	}

	/**
	 * Add customer id to address and save
	 *
	 * @param Market_AddressModel $address
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function saveAddress (Market_AddressModel $address)
	{
		$customer = $this->getSavedCustomer();
		$address->customerId = $customer->id;

		return craft()->market_address->saveAddress($address);
	}

	/**
	 * @param $billingId
	 * @param $shippingId
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function setLastUsedAddresses ($billingId, $shippingId)
	{
		$customer = $this->getSavedCustomer();

		if ($billingId)
		{
			$customer->lastUsedBillingAddressId = $billingId;
		}

		if ($shippingId)
		{
			$customer->lastUsedShippingAddressId = $shippingId;
		}

		return $this->save($customer);
	}

	/**
	 * @param $customerId
	 *
	 * @return array
	 */
	public function getAddressIds ($customerId)
	{
		$addresses = craft()->market_address->getAddressesByCustomerId($customerId);
		$ids = [];
		foreach ($addresses as $address)
		{
			$ids[] = $address->id;
		}

		return $ids;
	}

	/**
	 * Gets all customer by email address.
	 *
	 * @param $email
	 *
	 * @return array
	 */
	public function getByEmail ($email)
	{
		$customers = Market_CustomerRecord::model()->findAllByAttributes(['email' => $email]);

		return Market_CustomerModel::populateModels($customers);
	}

	/**
	 *
	 * @param Market_CustomerModel $customer
	 *
	 * @return mixed
	 */
	public function delete ($customer)
	{
		return Market_CustomerRecord::model()->deleteByPk($customer->id);
	}

	/**
	 * @param Event $event
	 *
	 * @throws Exception
	 */
	public function loginHandler (Event $event)
	{
		$username = $event->params['username'];
		$this->consolidateOrdersToUser($username);
	}

	/**
	 * @param string $username
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \Exception
	 */
	public function consolidateOrdersToUser ($username)
	{
		MarketDbHelper::beginStackedTransaction();

		try
		{

			/** @var UserModel $user */
			$user = craft()->users->getUserByUsernameOrEmail($username);

			$toCustomer = $this->getByUserId($user->id);

			if (!$toCustomer)
			{
				$toCustomer = new Market_CustomerModel();
				$toCustomer->email = $user->email;
				$toCustomer->userId = $user->id;
				$this->save($toCustomer);
			}

			$orders = craft()->market_order->getByEmail($toCustomer->email);

			foreach ($orders as $order)
			{
				// Only consolidate completed orders, not carts
				if ($order->dateOrdered)
				{
					$order->customerId = $toCustomer->id;
					$order->email = $toCustomer->email;
					craft()->market_order->save($order);
				}
			}

			MarketDbHelper::commitStackedTransaction();

			return true;
		}
		catch (\Exception $e)
		{
			MarketPlugin::log("Could not consolidate orders to username: ".$username.". Reason: ".$e->getMessage());
			MarketDbHelper::rollbackStackedTransaction();
		}
	}

	/**
	 * @param $id
	 *
	 * @return BaseModel
	 */
	public function getByUserId ($id)
	{
		$record = Market_CustomerRecord::model()->findByAttributes(['userId' => $id]);

		return Market_CustomerModel::populateModel($record);
	}

}