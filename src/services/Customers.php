<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Order as OrderHelper;
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
     * @param Customer|null $customer Defaults to the current customer in session if none is passing in.
     * @param bool $runValidation should we validate this address before saving.
     * @return bool
     * @throws Exception
     */
    public function saveAddress(Address $address, Customer $customer = null, bool $runValidation = true): bool
    {
        // default to customer in session.
        if (null === $customer) {
            $customer = $this->_getSavedCustomer();
        }

        if (Plugin::getInstance()->getAddresses()->saveAddress($address, $runValidation)) {
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
        $customerRecord->primaryBillingAddressId = $customer->primaryBillingAddressId;
        $customerRecord->primaryShippingAddressId = $customer->primaryShippingAddressId;

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
        // Remove the old customer from the session.
        $this->forgetCustomer();
        /** @var User $user */
        $user = $event->identity;
        $this->consolidateOrdersToUser($user);

        // Recover previous cart(s) of user
        $previousOrder = null;
        $cart = Plugin::getInstance()->getCarts()->getCart(true);
        $previousOrders = Order::find()->isCompleted(false)->user($user)->all();
        foreach ($previousOrders as $previousOrder) {
            if ($cart->id != $previousOrder->id) {
                OrderHelper::mergeOrders($cart, $previousOrder);
            }
        }
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
            Craft::error('Could not consolidate orders to user ' . $user->username . ': ' . $e->getMessage(), __METHOD__);
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
        $orderAddressesMutated = $this->_copyAddressesToOrder($order);

        $this->_createUserFromOrder($order);

        if ($orderAddressesMutated) {
            Craft::$app->getElements()->saveElement($order, false);
        }
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
                throw new Exception('Error saving customer: ' . $errors);
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
                'primaryBillingAddressId',
                'primaryShippingAddressId'
            ])
            ->from(['{{%commerce_customers}}']);
    }

    /**
     * @param Order $order
     * @return bool
     * @throws \yii\db\Exception
     */
    private function _copyAddressesToOrder(Order $order): bool
    {
        $mutated = false;
        // Now duplicate the addresses on the order
        $addressesService = Plugin::getInstance()->getAddresses();
        if ($order->billingAddress) {
            $snapshotBillingAddress = new Address($order->billingAddress->toArray([
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
            $originalBillingAddressId = $snapshotBillingAddress->id;
            $snapshotBillingAddress->id = null;
            if ($addressesService->saveAddress($snapshotBillingAddress, false)) {
                $mutated = true;
                $order->setBillingAddress($snapshotBillingAddress);
            } else {
                Craft::error(Craft::t('commerce', 'Unable to duplicate the billing address on order completion. Original billing address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalBillingAddressId, 'orderId' => $order->id]), __METHOD__);
            }
        }

        if ($order->shippingAddress) {
            $snapshotShippingAddress = new Address($order->shippingAddress->toArray([
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
            $originalShippingAddressId = $snapshotShippingAddress->id;
            $snapshotShippingAddress->id = null;
            if ($addressesService->saveAddress($snapshotShippingAddress, false)) {
                $mutated = true;
                $order->setShippingAddress($snapshotShippingAddress);
            } else {
                Craft::error(Craft::t('commerce', 'Unable to duplicate the shipping address on order completion. Original shipping address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalShippingAddressId, 'orderId' => $order->id]), __METHOD__);
            }
        }

        return $mutated;
    }

    /**
     * @param Order $order
     * @return void
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    public function _createUserFromOrder(Order $order)
    {
        // Only do this if requested
        if (!$order->registerUserOnOrderComplete) {
            return;
        }

        // Only if on pro edition
        if (Craft::$app->getEdition() != Craft::Pro) {
            return;
        }

        // If a user is logged in, then don't create a user account
        if (Craft::$app->getUser()->getIdentity()) {
            return;
        }

        // order already has a registered user associated
        if ($order->getUser()) {
            return;
        }

        // can't create a user without an email
        if (!$order->email) {
            return;
        }

        // already a user?
        $user = User::find()->email($order->email)->one();
        if ($user) {
            return;
        }

        // Need to associate the new user to the orders customer
        $customer = $order->getCustomer();
        if (!$customer) {
            return;
        }

        // Create a new user
        $user = new User();
        $user->email = $order->email;
        $user->unverifiedEmail = $order->email;
        $user->newPassword = null;
        $user->username = $order->email;
        $user->firstName = $order->billingAddress->firstName ?? '';
        $user->lastName = $order->billingAddress->lastName ?? '';
        $user->pending = true;
        $user->setScenario(Element::SCENARIO_ESSENTIALS); //  don't validate required custom fields.

        if (Craft::$app->getElements()->saveElement($user)) {
            Craft::$app->getUsers()->assignUserToDefaultGroup($user);
            $emailSent = Craft::$app->getUsers()->sendActivationEmail($user);

            if (!$emailSent) {
                Craft::warning('User saved, but couldn’t send activation email. Check your email settings.', __METHOD__);
            }

            $customer->userId = $user->id;
            Plugin::getInstance()->getCustomers()->saveCustomer($customer, false);
        } else {
            $errors = $user->getErrors();
            Craft::warning('Could not create user on order completion.', __METHOD__);
            Craft::warning($errors, __METHOD__);
        }
    }
}
