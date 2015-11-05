<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Customer service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_CustomersService extends BaseApplicationComponent
{
    const SESSION_CUSTOMER = 'commerce_customer_cookie';

    /** @var Commerce_CustomerModel */
    private $customer = null;


    /**
     * Id of current customer record. Guaranteed not null
     *
     * @return int
     * @throws Exception
     */
    public function getCustomerId()
    {
        return $this->getSavedCustomer()->id;
    }

    /**
     * @return Commerce_CustomerModel
     * @throws Exception
     */
    private function getSavedCustomer()
    {
        $customer = $this->getCustomer();
        if (!$customer->id) {
            if ($this->saveCustomer($customer)) {
                craft()->session->add(self::SESSION_CUSTOMER, $customer->id);
            } else {
                $errors = implode(', ', $customer->getAllErrors());
                throw new Exception('Error saving customer: ' . $errors);
            }
        }

        return $customer;
    }

    /**
     * @return Commerce_CustomerModel
     */
    public function getCustomer()
    {
        if ($this->customer === null) {
            $user = craft()->userSession->getUser();

            if ($user) {
                $record = Commerce_CustomerRecord::model()->findByAttributes(['userId' => $user->id]);
            } else {
                $id = craft()->session->get(self::SESSION_CUSTOMER);
                if ($id) {
                    $record = Commerce_CustomerRecord::model()->findById($id);
                    // If there is a customer record but it is associated with a real user, don't use it when guest.
                    if ($record && $record->userId) {
                        $record = false;
                    }
                }
            }

            if (empty($record)) {
                $record = new Commerce_CustomerRecord;

                if ($user) {
                    $record->userId = $user->id;
                    $record->email = $user->email;
                }
            }

            $this->customer = Commerce_CustomerModel::populateModel($record);
        }

        return $this->customer;
    }

    /**
     * @param Commerce_CustomerModel $customer
     *
     * @return bool
     * @throws Exception
     */
    public function saveCustomer(Commerce_CustomerModel $customer)
    {
        if (!$customer->id) {
            $customerRecord = new Commerce_CustomerRecord();
        } else {
            $customerRecord = Commerce_CustomerRecord::model()->findById($customer->id);

            if (!$customerRecord) {
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

        if (!$customer->hasErrors()) {
            $customerRecord->save(false);
            $customer->id = $customerRecord->id;

            return true;
        }

        return false;
    }

    /**
     * @param \CDbCriteria|array $criteria
     *
     * @return Commerce_CustomerModel[]
     */
    public function getAllCustomers($criteria = [])
    {
        $records = Commerce_CustomerRecord::model()->findAll($criteria);

        return Commerce_CustomerModel::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Commerce_CustomerModel|null
     */
    public function getCustomerById($id)
    {
        $result = Commerce_CustomerRecord::model()->findById($id);

        if ($result) {
            return Commerce_CustomerModel::populateModel($result);
        }

        return null;
    }

    /**
     * @return bool
     */
    public function isCustomerSaved()
    {
        return !!$this->getCustomer()->id;
    }

    /**
     * Add customer id to address and save
     *
     * @param Commerce_AddressModel $address
     *
     * @return bool
     * @throws Exception
     */
    public function saveAddress(Commerce_AddressModel $address)
    {
        $customer = $this->getSavedCustomer();
        $address->customerId = $customer->id;

        return craft()->commerce_addresses->saveAddress($address);
    }

    /**
     * @param $billingId
     * @param $shippingId
     *
     * @return bool
     * @throws Exception
     */
    public function setLastUsedAddresses($billingId, $shippingId)
    {
        $customer = $this->getSavedCustomer();

        if ($billingId) {
            $customer->lastUsedBillingAddressId = $billingId;
        }

        if ($shippingId) {
            $customer->lastUsedShippingAddressId = $shippingId;
        }

        return $this->saveCustomer($customer);
    }

    /**
     * @param $customerId
     *
     * @return array
     */
    public function getAddressIds($customerId)
    {
        $addresses = craft()->commerce_addresses->getAddressesByCustomerId($customerId);
        $ids = [];
        foreach ($addresses as $address) {
            $ids[] = $address->id;
        }

        return $ids;
    }

    /**
     * Gets all customers by email address.
     *
     * @param $email
     *
     * @return array
     */
    public function getAllCustomersByEmail($email)
    {
        $customers = Commerce_CustomerRecord::model()->findAllByAttributes(['email' => $email]);

        return Commerce_CustomerModel::populateModels($customers);
    }

    /**
     *
     * @param Commerce_CustomerModel $customer
     *
     * @return mixed
     */
    public function deleteCustomer($customer)
    {
        return Commerce_CustomerRecord::model()->deleteByPk($customer->id);
    }

    /**
     * @param Event $event
     *
     * @throws Exception
     */
    public function loginHandler(Event $event)
    {
        $username = $event->params['username'];
        $this->consolidateOrdersToUser($username);
    }

    /**
     * @param Event $event
     *
     * @throws Exception
     */
    public function saveUserHandler(Event $event)
    {
        $user = $event->params['user'];
        $customer = $this->getCustomerByUserId($user->id);

        // Sync the users email with the customer record.
        if($customer){
            if($customer->email != $user->email){
                $customer->email = $user->email;
                if(!$this->saveCustomer($customer)){
                    $error = Craft::t('Could not sync user’s email to customers record. CustomerId:{customerId} UserId:{userId}',
                        ['customerId' => $customer->id, 'userId' => $user->id]);
                    CommercePlugin::log($error);
                };
            }
        }
    }


    /**
     * @param string $username
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function consolidateOrdersToUser($username)
    {
        CommerceDbHelper::beginStackedTransaction();

        try {

            /** @var UserModel $user */
            $user = craft()->users->getUserByUsernameOrEmail($username);

            $toCustomer = $this->getCustomerByUserId($user->id);

            if (!$toCustomer) {
                $toCustomer = new Commerce_CustomerModel();
                $toCustomer->email = $user->email;
                $toCustomer->userId = $user->id;
                $this->saveCustomer($toCustomer);
            }

            $orders = craft()->commerce_orders->getOrdersByEmail($toCustomer->email);

            foreach ($orders as $order) {
                // Only consolidate completed orders, not carts
                if ($order->dateOrdered) {
                    $order->customerId = $toCustomer->id;
                    $order->email = $toCustomer->email;
                    craft()->commerce_orders->saveOrder($order);
                }
            }

            CommerceDbHelper::commitStackedTransaction();

            return true;
        } catch (\Exception $e) {
            CommercePlugin::log("Could not consolidate orders to username: " . $username . ". Reason: " . $e->getMessage());
            CommerceDbHelper::rollbackStackedTransaction();
        }
    }

    /**
     * @param $id
     *
     * @return Commerce_CustomerModel|null
     */
    public function getCustomerByUserId($id)
    {
        $result = Commerce_CustomerRecord::model()->findByAttributes(['userId' => $id]);

        if ($result) {
            return Commerce_CustomerModel::populateModel($result);
        }

        return null;
    }

}
