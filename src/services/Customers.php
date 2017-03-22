<?php
namespace craft\commerce\services;

use craft\commerce\helpers\Db;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\records\Customer as CustomerRecord;
use craft\commerce\records\CustomerAddress as CustomerAddressRecord;
use yii\base\Component;

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
class Customers extends Component
{
    const SESSION_CUSTOMER = 'commerce_customer_cookie';

    /** @var Customer */
    private $_customer = null;

    /**
     * @param \CDbCriteria|array $criteria
     *
     * @return Customer[]
     */
    public function getAllCustomers($criteria = [])
    {
        $records = CustomerRecord::model()->findAll($criteria);

        return Customer::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Customer|null
     */
    public function getCustomerById($id)
    {
        $result = $this->_createCustomersQuery()
            ->where('customers.id = :xid', [':xid' => $id])
            ->queryRow();

        if ($result) {
            return new Customer($result);
        }

        return null;
    }

    /**
     * Returns a DbCommand object prepped for retrieving customers.
     *
     * @return DbCommand
     */
    private function _createCustomersQuery()
    {
        return Craft::$app->getDb()->createCommand()
            ->select('customers.id, customers.userId, customers.email, customers.lastUsedBillingAddressId, customers.lastUsedShippingAddressId')
            ->from('commerce_customers customers')
            ->order('id');
    }

    /**
     * @return bool
     */
    public function isCustomerSaved()
    {
        return !!$this->getCustomer()->id;
    }

    /**
     * @return Customer
     */
    public function getCustomer()
    {
        if ($this->_customer === null) {
            $user = Craft::$app->getUser()->getUser();

            if ($user) {
                $record = $this->_createCustomersQuery()
                    ->where('customers.userId = :userId', [':userId' => $user->id])
                    ->queryRow();

                if ($record) {
                    craft()->session->add(self::SESSION_CUSTOMER, $record['id']);
                }
            } else {
                $id = craft()->session->get(self::SESSION_CUSTOMER);
                if ($id) {
                    $record = $this->_createCustomersQuery()
                        ->where('customers.id = :xid', [':xid' => $id])
                        ->queryRow();

                    // If there is a customer record but it is associated with a real user, don't use it when guest.
                    if ($record && $record['userId']) {
                        $record = null;
                    }
                }
            }

            if (empty($record)) {
                $record = [];

                if ($user) {
                    $record['userId'] = $user->id;
                    $record['email'] = $user->email;
                }
            }

            $this->_customer = new Customer($record);
        }

        return $this->_customer;
    }

    /**
     * Add customer id to address and save
     *
     * @param Address $address
     *
     * @return bool
     * @throws Exception
     */
    public function saveAddress(Address $address)
    {
        $customer = $this->getSavedCustomer();
        if (Plugin::getInstance()->getAddresses()->saveAddress($address)) {

            $customerAddress = CustomerAddressRecord::find()->where([
                'customerId' => $customer->id,
                'addressId' => $address->id
            ]);

            if (!$customerAddress) {
                $customerAddress = new CustomerAddressRecord();
            }

            $customerAddress->customerId = $customer->id;
            $customerAddress->addressId = $address->id;
            if ($customerAddress->save()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Customer
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
                throw new Exception('Error saving customer: '.$errors);
            }
        }

        return $customer;
    }

    /**
     * @param Customer $customer
     *
     * @return bool
     * @throws Exception
     */
    public function saveCustomer(Customer $customer)
    {
        if (!$customer->id) {
            $customerRecord = new CustomerRecord();
        } else {
            $customerRecord = CustomerRecord::findOne($customer->id);

            if (!$customerRecord) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No customer exists with the ID “{id}”',
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
     * @param $customerId
     *
     * @return array
     */
    public function getAddressIds($customerId)
    {
        $addresses = Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($customerId);
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
        $results = $this->_createCustomersQuery()
            ->where('customers.email = :email', [':email' => $email])
            ->queryAll();

        return Customer::populateModels($results);
    }

    /**
     *
     * @param Customer $customer
     *
     * @return mixed
     */
    public function deleteCustomer($customer)
    {
        return CustomerRecord::model()->deleteByPk($customer->id);
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
     * Forgets a Customer by deleting the customer from session and request.
     */
    public function forgetCustomer()
    {
        $this->_customer = null;
        craft()->session->remove(self::SESSION_CUSTOMER);
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
        Db::beginStackedTransaction();

        try {

            /** @var UserModel $user */
            $user = craft()->users->getUserByUsernameOrEmail($username);

            $toCustomer = $this->getCustomerByUserId($user->id);

            // The user has no previous customer record, create one.
            if (!$toCustomer) {
                $toCustomer = new Customer();
                $toCustomer->email = $user->email;
                $toCustomer->userId = $user->id;
                $this->saveCustomer($toCustomer);
            }

            // Grab all the orders for the customer.
            $orders = Plugin::getInstance()->getOrders()->getOrdersByEmail($toCustomer->email);

            // Assign each completed order to the users' customer and update the email.
            foreach ($orders as $order) {
                // Only consolidate completed orders, not carts
                if ($order->isCompleted) {
                    $order->customerId = $toCustomer->id;
                    $order->email = $toCustomer->email;
                    Plugin::getInstance()->getOrders()->saveOrder($order);
                }
            }

            Db::commitStackedTransaction();

            return true;
        } catch (\Exception $e) {
            CommercePlugin::log("Could not consolidate orders to username: ".$username.". Reason: ".$e->getMessage());
            Db::rollbackStackedTransaction();
        }
    }

    /**
     * @param $id
     *
     * @return Customer|null
     */
    public function getCustomerByUserId($id)
    {
        $result = $this->_createCustomersQuery()
            ->where('customers.userId = :xid', [':xid' => $id])
            ->queryRow();

        if ($result) {
            return new Customer($result);
        }

        return null;
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
     * @param Order $order
     */
    public function orderCompleteHandler($order)
    {
        // set the last used addresses before duplicating the addresses on the order
        if (!craft()->isConsole()) {
            if ($order->customerId == $this->getCustomerId()) {
                $this->setLastUsedAddresses($order->billingAddressId, $order->shippingAddressId);
            }
        }

        // Now duplicate the addresses on the order
        if ($order->billingAddress) {
            $snapShotBillingAddress = new Address($order->billingAddress);
            $originalBillingAddressId = $snapShotBillingAddress->id;
            $snapShotBillingAddress->id = null;
            if (Plugin::getInstance()->getAddresses()->saveAddress($snapShotBillingAddress, false)) {
                $order->billingAddressId = $snapShotBillingAddress->id;
            } else {
                CommercePlugin::log(Craft::t('commerce', 'commerce', 'Unable to duplicate the billing address on order completion. Original billing address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalBillingAddressId, 'orderId' => $order->id]), LogLevel::Error, true);
            }
        }

        if ($order->shippingAddress) {
            $snapShotShippingAddress = new Address($order->shippingAddress);
            $originalShippingAddressId = $snapShotShippingAddress->id;
            $snapShotShippingAddress->id = null;
            if (Plugin::getInstance()->getAddresses()->saveAddress($snapShotShippingAddress, false)) {
                $order->shippingAddressId = $snapShotShippingAddress->id;
            } else {
                CommercePlugin::log(Craft::t('commerce', 'commerce', 'Unable to duplicate the shipping address on order completion. Original shipping address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalShippingAddressId, 'orderId' => $order->id]), LogLevel::Error, true);
            }
        }

        Plugin::getInstance()->getOrders()->saveOrder($order);
    }

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


    // Private Methods
    // =========================================================================

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
        if ($customer) {
            if ($customer->email != $user->email) {
                $customer->email = $user->email;
                if (!$this->saveCustomer($customer)) {
                    $error = Craft::t('commerce', 'commerce', 'Could not sync user’s email to customers record. CustomerId:{customerId} UserId:{userId}',
                        ['customerId' => $customer->id, 'userId' => $user->id]);
                    CommercePlugin::log($error);
                };
            }

            $orders = Plugin::getInstance()->getOrders()->getOrdersByCustomer($customer);

            foreach ($orders as $order) {
                $order->email = $user->email;
                Plugin::getInstance()->getOrders()->saveOrder($order);
            }
        }
    }
}
