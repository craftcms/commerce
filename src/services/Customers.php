<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\Plugin;
use craft\commerce\records\Customer as CustomerRecord;
use craft\commerce\records\CustomerAddress as CustomerAddressRecord;
use craft\db\Query;
use craft\elements\User;
use yii\base\Component;
use yii\base\Event;
use yii\base\Exception;
use yii\web\UserEvent;

/**
 * Customer service.
 *
 * @property array|Customer[] $allCustomers
 * @property Customer $customer
 * @property int $customerId id of current customer record
 * @property Customer $savedCustomer
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Customers extends Component
{
    // Constants
    // =========================================================================

    const SESSION_CUSTOMER = 'commerce_customer';

    // Properties
    // =========================================================================

    /**
     * @var Customer
     */
    private $_customer;

    // Public Methods
    // =========================================================================

    /**
     * Get all customers.
     *
     * @return Customer[]
     */
    public function getAllCustomers(): array
    {
        $rows = $this->_createCustomerQuery()
            ->all();

        $customers = [];

        foreach ($rows as $row) {
            $customers[] = new Customer($row);
        }

        return $customers;
    }

    /**
     * Get a customer by its ID.
     *
     * @param int $id
     * @return Customer|null
     */
    public function getCustomerById(int $id)
    {
        $row = $this->_createCustomerQuery()
            ->where(['id' => $id])
            ->one();

        return $row ? new Customer($row) : null;
    }

    /**
     * Return true, if the current customer is saved to the database.
     *
     * @return bool
     */
    public function isCustomerSaved(): bool
    {
        return (bool)$this->getCustomer()->id;
    }

    /**
     * Get the current customer.
     *
     * @return Customer
     */
    public function getCustomer(): Customer
    {
        $session = Craft::$app->getSession();

        if ($this->_customer === null) {
            $user = Craft::$app->getUser()->getIdentity();

            $customer = null;

            // Find user's customer or the current customer in the session.
            if ($user) {
                $customer = $this->getCustomerByUserId($user->id);

                if ($customer) {
                    $session->set(self::SESSION_CUSTOMER, $customer->id);
                }
            } else if ($session->getHasSessionId() || $session->getIsActive()) {
                $id = $session->get(self::SESSION_CUSTOMER);

                if ($id) {
                    $customer = $this->getCustomerById($id);

                    // If there is a customer record but it is associated with a real user, don't use it when guest.
                    if ($customer && $customer->userId) {
                        $customer = null;
                    }
                }
            }

            if ($customer === null) {
                $customer = new Customer();

                if ($user) {
                    $customer->userId = $user->id;
                }
            }

            $this->_customer = $customer;
        }

        return $this->_customer;
    }

    /**
     * Associates an address with the saved customer, and saves the address.
     *
     * @param Address $address
     * @return bool
     * @throws Exception
     */
    public function saveAddress(Address $address): bool
    {
        $customer = $this->_getSavedCustomer();

        if (Plugin::getInstance()->getAddresses()->saveAddress($address)) {
            $customerAddress = CustomerAddressRecord::find()->where([
                'customerId' => $customer->id,
                'addressId' => $address->id
            ])->one();

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
     * Save a customer by its model.
     *
     * @param Customer $customer
     * @param bool $runValidation should we validate this customer before saving.
     * @return bool
     * @throws Exception
     */
    public function saveCustomer(Customer $customer, bool $runValidation = true): bool
    {
        if (!$customer->id) {
            $customerRecord = new CustomerRecord();
        } else {
            $customerRecord = CustomerRecord::findOne($customer->id);

            if (!$customerRecord) {
                throw new Exception(Craft::t('commerce', 'No customer exists with the ID “{id}”',
                    ['id' => $customer->id]));
            }
        }

        if ($runValidation && !$customer->validate()) {
            Craft::info('Customer not saved due to validation error.', __METHOD__);

            return false;
        }

        $customerRecord->userId = $customer->userId;
        $customerRecord->lastUsedBillingAddressId = $customer->lastUsedBillingAddressId;
        $customerRecord->lastUsedShippingAddressId = $customer->lastUsedShippingAddressId;

        $customerRecord->validate();
        $customer->addErrors($customerRecord->getErrors());

        $customerRecord->save(false);
        $customer->id = $customerRecord->id;

        return true;
    }

    /**
     * Get all address IDs for a customer by its ID.
     *
     * @param $customerId
     * @return array
     */
    public function getAddressIds($customerId): array
    {
        $ids = [];

        if ($customerId) {
            $addresses = Plugin::getInstance()->getAddresses()->getAddressesByCustomerId($customerId);

            foreach ($addresses as $address) {
                $ids[] = $address->id;
            }
        }

        return $ids;
    }

    /**
     * Delete a customer.
     *
     * @param Customer $customer
     * @return mixed
     */
    public function deleteCustomer($customer)
    {
        $customer = CustomerRecord::findOne($customer->id);

        if ($customer) {
            return $customer->delete();
        }

        return null;
    }

    /**
     * When a user logs in, consolidate all his/her orders.
     *
     * @param UserEvent $event
     */
    public function loginHandler(UserEvent $event)
    {
        // Remove the customer from session.
        $this->forgetCustomer();
        /** @var User $user */
        $user = $event->identity;
        $this->consolidateOrdersToUser($user);
    }

    /**
     * Forgets a Customer by deleting the customer from session and request.
     */
    public function forgetCustomer()
    {
        $this->_customer = null;

        $session = Craft::$app->getSession();
        if ($session->getHasSessionId() || $session->getIsActive()) {
            $session->remove(self::SESSION_CUSTOMER);
        }
    }

    /**
     * Assigns guest orders to a user.
     *
     * @param User $user
     * @param Order[]|null the orders con consolidate. If null, all guest orders associated with the user's email will be fetched
     * @return bool
     */
    public function consolidateOrdersToUser(User $user, array $orders = null): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $toCustomer = $this->getCustomerByUserId($user->id);

            // The user has no previous customer record, create one.
            if (!$toCustomer) {
                $toCustomer = new Customer();
                $toCustomer->setUser($user);
                if (!$this->saveCustomer($toCustomer)) {
                    return false;
                }
            }

            if ($orders === null) {
                // Shouldn't really happen as all users should have an email.
                if (!$toCustomer->email) {
                    return false;
                }

                // Grab all the orders for the customer.
                $orders = Plugin::getInstance()->getOrders()->getOrdersByEmail($toCustomer->email);
            }

            // Assign each completed order to the users' customer and update the email.
            foreach ($orders as $order) {
                $belongsToAnotherUser = $order->getCustomer() && $order->getCustomer()->getUser();
                // Only consolidate completed orders, not carts and orders that don't belong to another user.

                if ($order->isCompleted && !$belongsToAnotherUser) {
                    $order->customerId = $toCustomer->id;
                    Craft::$app->getElements()->saveElement($order);
                }
            }

            $transaction->commit();
        } catch (\Exception $e) {
            Craft::error('Could not consolidate orders to user '.$user->username.': '.$e->getMessage(), __METHOD__);
            $transaction->rollBack();
            return false;
        }

        return true;
    }

    /**
     * Get a customer by user ID. Returns null, if it doesn't exist.
     *
     * @param $id
     * @return Customer|null
     */
    public function getCustomerByUserId($id)
    {

        $row = $this->_createCustomerQuery()
            ->where(['userId' => $id])
            ->one();

        return $row ? new Customer($row) : null;
    }

    /**
     * Returns the user groups of the user param but defaults to the current user
     *
     * @param User|null $user
     * @return array
     */
    public function getUserGroupIdsForUser(User $user = null): array
    {
        $groupIds = [];
        $currentUser = $user ?? Craft::$app->getUser()->getIdentity();

        if ($currentUser) {
            foreach ($currentUser->getGroups() as $group) {
                $groupIds[] = $group->id;
            }
        }

        return $groupIds;
    }

    /**
     * Handle the user logout.
     *
     * @param UserEvent $event
     * @throws Exception
     */
    public function logoutHandler(UserEvent $event)
    {
        // Reset the sessions customer.
        Plugin::getInstance()->getCarts()->forgetCart();
        $this->forgetCustomer();
    }

    /**
     * Sets the last used addresses on the customer on order completion.
     * Duplicates the address records used for the order so they are independent to the
     * customers address book.
     *
     * @param Order $order
     */
    public function orderCompleteHandler($order)
    {
        // set the last used addresses before duplicating the addresses on the order
        if (!Craft::$app->request->isConsoleRequest) {
            if ($order->customerId == $this->getCustomerId()) {
                $this->setLastUsedAddresses($order->billingAddressId, $order->shippingAddressId);
            }
        }

        // Now duplicate the addresses on the order
        $addressesService = Plugin::getInstance()->getAddresses();
        if ($order->billingAddress) {
            $snapShotBillingAddress = new Address($order->billingAddress->toArray([
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
                ]
            ));
            $originalBillingAddressId = $snapShotBillingAddress->id;
            $snapShotBillingAddress->id = null;
            if ($addressesService->saveAddress($snapShotBillingAddress, false)) {
                $order->billingAddressId = $snapShotBillingAddress->id;
            } else {
                Craft::error(Craft::t('commerce', 'Unable to duplicate the billing address on order completion. Original billing address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalBillingAddressId, 'orderId' => $order->id]), __METHOD__);
            }
        }

        if ($order->shippingAddress) {
            $snapShotShippingAddress = new Address($order->shippingAddress->toArray([
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
                ]
            ));
            $originalShippingAddressId = $snapShotShippingAddress->id;
            $snapShotShippingAddress->id = null;
            if ($addressesService->saveAddress($snapShotShippingAddress, false)) {
                $order->shippingAddressId = $snapShotShippingAddress->id;
            } else {
                Craft::error(Craft::t('commerce', 'Unable to duplicate the shipping address on order completion. Original shipping address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalShippingAddressId, 'orderId' => $order->id]), __METHOD__);
            }
        }

        Craft::$app->getElements()->saveElement($order);
    }

    /**
     * Id of current customer record. Guaranteed not null
     *
     * @return int
     * @throws Exception
     */
    public function getCustomerId(): int
    {
        return $this->_getSavedCustomer()->id;
    }

    /**
     * Set the last used billing and shipping addresses for the current customer.
     *
     * @param int $billingId ID of billing address.
     * @param int $shippingId ID of shipping address.
     * @return bool
     * @throws Exception if failed to save addresses on customer.
     */
    public function setLastUsedAddresses($billingId, $shippingId): bool
    {
        $customer = $this->_getSavedCustomer();

        if ($billingId) {
            $customer->lastUsedBillingAddressId = $billingId;
        }

        if ($shippingId) {
            $customer->lastUsedShippingAddressId = $shippingId;
        }

        return $this->saveCustomer($customer);
    }

    /**
     * Handle a saved user.
     *
     * @param Event $event
     * @throws Exception
     */
    public function saveUserHandler(Event $event)
    {
        $user = $event->sender;
        $customer = $this->getCustomerByUserId($user->id);

        // Sync the users email with the customer record.
        if ($customer) {
            $orders = Plugin::getInstance()->getOrders()->getOrdersByCustomer($customer);

            foreach ($orders as $order) {
                // Email will be set to the users email since on re-save as $order->getEmail() returns the related registered user's email.
                Craft::$app->getElements()->saveElement($order);
            }
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Get the current customer.
     *
     * @return Customer
     * @throws Exception if failed to save customer.
     */
    private function _getSavedCustomer(): Customer
    {
        $customer = $this->getCustomer();

        if (!$customer->id) {
            if ($this->saveCustomer($customer)) {
                Craft::$app->getSession()->set(self::SESSION_CUSTOMER, $customer->id);
            } else {
                $errors = implode(', ', $customer->errors);
                throw new Exception('Error saving customer: '.$errors);
            }
        }

        return $customer;
    }

    /**
     * Returns a Query object prepped for retrieving Order Adjustment.
     *
     * @return Query The query object.
     */
    private function _createCustomerQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'userId',
                'lastUsedBillingAddressId',
                'lastUsedShippingAddressId'
            ])
            ->from(['{{%commerce_customers}}']);
    }
}
