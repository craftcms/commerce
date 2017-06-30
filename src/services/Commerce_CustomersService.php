<?php
namespace Craft;


/**
 * Customer service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_CustomersService extends BaseApplicationComponent
{
    const SESSION_CUSTOMER = 'commerce_customer_cookie';

    /** @var Commerce_CustomerModel */
    private $_customer = null;


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
        if (!$customer->id)
        {
            if ($this->saveCustomer($customer))
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
     * @return Commerce_CustomerModel
     */
    public function getCustomer()
    {
        if ($this->_customer === null)
        {
            $user = craft()->userSession->getUser();

            if ($user)
            {
                $record = $this->_createCustomersQuery()
                    ->where('customers.userId = :userId', [':userId' => $user->id])
                    ->queryRow();

                if ($record)
                {
                    craft()->session->add(self::SESSION_CUSTOMER, $record['id']);
                }
            }
            else
            {
                $id = craft()->session->get(self::SESSION_CUSTOMER);
                if ($id)
                {
                    $record = $this->_createCustomersQuery()
                        ->where('customers.id = :xid', [':xid' => $id])
                        ->queryRow();

                    // If there is a customer record but it is associated with a real user, don't use it when guest.
                    if ($record && $record['userId'])
                    {
                        $record = null;
                    }
                }
            }

            if (empty($record))
            {
                $record = [];

                if ($user)
                {
                    $record['userId'] = $user->id;
                    $record['email'] = $user->email;
                }
            }

            $this->_customer = Commerce_CustomerModel::populateModel($record);
        }

        return $this->_customer;
    }

    /**
     * Forgets a Customer by deleting the customer from session and request.
     */
    public function forgetCustomer()
    {
        $this->_customer = null;
        craft()->session->remove(self::SESSION_CUSTOMER);
    }

    /**
     * @param Commerce_CustomerModel $customer
     *
     * @return bool
     * @throws Exception
     */
    public function saveCustomer(Commerce_CustomerModel $customer)
    {
        if (!$customer->id)
        {
            $customerRecord = new Commerce_CustomerRecord();
        }
        else
        {
            $customerRecord = Commerce_CustomerRecord::model()->findById($customer->id);

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

        // If the customer is attached to a user, always use the user's email address.
        if($user = craft()->users->getUserById($customer->userId))
        {
            $customerRecord->email = $customer->email = $user->email;
        }

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
        $result = $this->_createCustomersQuery()
            ->where('customers.id = :xid', [':xid' => $id])
            ->queryRow();

        if ($result)
        {
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
        if (craft()->commerce_addresses->saveAddress($address))
        {

            $customerAddress = Commerce_CustomerAddressRecord::model()->findByAttributes([
                'customerId' => $customer->id,
                'addressId'  => $address->id
            ]);

            if (!$customerAddress)
            {
                $customerAddress = new Commerce_CustomerAddressRecord;
            }

            $customerAddress->customerId = $customer->id;
            $customerAddress->addressId = $address->id;
            if ($customerAddress->save())
            {
                return true;
            }
        }

        return false;
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

        if ($billingId)
        {
            $customer->lastUsedBillingAddressId = $billingId;
        }

        if ($shippingId)
        {
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
        foreach ($addresses as $address)
        {
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
        $results = $this->_createCustomersQuery()
            ->where('customers.email = :email', [':email' => $email])
            ->queryAll();

        return Commerce_CustomerModel::populateModels($results);
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
        // Remove the customer from session.
        $this->forgetCustomer();

        $username = $event->params['username'];
        $this->consolidateOrdersToUser($username);
    }

    /**
     * @param Event $event
     *
     * @throws Exception
     */
    public function logoutHandler(Event $event)
    {
        // Reset the sessions customer.
        $this->forgetCustomer();
    }

    /**
     * Sets the last used addresses on the customer on order completion.
     *
     * Duplicates the address records used for the order so they are independent to the
     * customers address book.
     *
     * @param Commerce_OrderModel $order
     */
    public function orderCompleteHandler($order)
    {
        // set the last used addresses before duplicating the addresses on the order
        if (!craft()->isConsole())
        {
            if ($order->customerId == $this->getCustomerId())
            {
                $this->setLastUsedAddresses($order->billingAddressId, $order->shippingAddressId);
            }
        }

        // Now duplicate the addresses on the order
        if ($order->billingAddress)
        {
            $snapShotBillingAddress = Commerce_AddressModel::populateModel($order->billingAddress);
            $originalBillingAddressId = $snapShotBillingAddress->id;
            $snapShotBillingAddress->id = null;
            if (craft()->commerce_addresses->saveAddress($snapShotBillingAddress, false))
            {
                $order->billingAddressId = $snapShotBillingAddress->id;
            }else{
                CommercePlugin::log(Craft::t('Unable to duplicate the billing address on order completion. Original billing address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalBillingAddressId, 'orderId'   => $order->id]), LogLevel::Error, true);
            }
        }

        if ($order->shippingAddress)
        {
            $snapShotShippingAddress = Commerce_AddressModel::populateModel($order->shippingAddress);
            $originalShippingAddressId = $snapShotShippingAddress->id;
            $snapShotShippingAddress->id = null;
            if (craft()->commerce_addresses->saveAddress($snapShotShippingAddress, false))
            {
                $order->shippingAddressId = $snapShotShippingAddress->id;
            }else{
                CommercePlugin::log(Craft::t('Unable to duplicate the shipping address on order completion. Original shipping address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalShippingAddressId, 'orderId'   => $order->id]), LogLevel::Error, true);
            }
        }

        craft()->commerce_orders->saveOrder($order);
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
        if ($customer)
        {
            if ($customer->email != $user->email)
            {
                $customer->email = $user->email;
                if (!$this->saveCustomer($customer))
                {
                    $error = Craft::t('Could not sync user’s email to customers record. CustomerId:{customerId} UserId:{userId}',
                        ['customerId' => $customer->id, 'userId' => $user->id]);
                    CommercePlugin::log($error);
                };
            }

            $orders = craft()->commerce_orders->getOrdersByCustomer($customer);

            foreach ($orders as $order)
            {
                $order->email = $user->email;
                craft()->commerce_orders->saveOrder($order);
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
        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

        try
        {

            /** @var UserModel $user */
            $user = craft()->users->getUserByUsernameOrEmail($username);

            $toCustomer = $this->getCustomerByUserId($user->id);

            // The user has no previous customer record, create one.
            if (!$toCustomer)
            {
                $toCustomer = new Commerce_CustomerModel();
                $toCustomer->email = $user->email;
                $toCustomer->userId = $user->id;
                $this->saveCustomer($toCustomer);
            }

            // Grab all the orders for the customer.
            $orders = craft()->commerce_orders->getOrdersByEmail($toCustomer->email);

            // Assign each completed order to the users' customer and update the email.
            foreach ($orders as $order)
            {
                // Only consolidate completed orders, not carts
                if ($order->isCompleted)
                {
                    $order->customerId = $toCustomer->id;
                    $order->email = $toCustomer->email;
                    craft()->commerce_orders->saveOrder($order);
                }
            }

            if ($transaction !== null)
            {
                $transaction->commit();
            }

            return true;
        }
        catch (\Exception $e)
        {
            CommercePlugin::log("Could not consolidate orders to username: ".$username.". Reason: ".$e->getMessage());
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
        }
    }

    /**
     * @param $id
     *
     * @return Commerce_CustomerModel|null
     */
    public function getCustomerByUserId($id)
    {
        $result = $this->_createCustomersQuery()
            ->where('customers.userId = :xid', [':xid' => $id])
            ->queryRow();

        if ($result)
        {
            return Commerce_CustomerModel::populateModel($result);
        }

        return null;
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns a DbCommand object prepped for retrieving customers.
     *
     * @return DbCommand
     */
    private function _createCustomersQuery()
    {
        return craft()->db->createCommand()
            ->select('customers.id, customers.userId, customers.email, customers.lastUsedBillingAddressId, customers.lastUsedShippingAddressId')
            ->from('commerce_customers customers')
            ->order('id');
    }
}
