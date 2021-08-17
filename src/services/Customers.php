<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Element;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\CustomerAddressEvent;
use craft\commerce\events\CustomerEvent;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\Plugin;
use craft\commerce\records\Customer as CustomerRecord;
use craft\commerce\records\CustomerAddress as CustomerAddressRecord;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\User;
use craft\elements\User as UserElement;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\events\ModelEvent;
use craft\helpers\ArrayHelper;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Component;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\Expression;
use yii\db\StaleObjectException;
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
    const SESSION_CUSTOMER = 'commerce_customer';

    /**
     * @var Customer|null
     */
    private ?Customer $_customer = null;

    /**
     * @event CustomerEvent The event that is triggered before customer details is saved.
     * @since 3.2.9
     *
     * ```php
     * Event::on(
     * Customers::class,
     * Customers::EVENT_BEFORE_SAVE_CUSTOMER,
     *     function(CustomerEvent $event) {
     *         // @var Customer $customer
     *         $customer = $event->customer;;
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_SAVE_CUSTOMER = 'beforeSaveCustomer';

    /**
     * @event CustomerEvent The event that is triggered after customer details is saved.
     * @since 3.2.9
     *
     * ```php
     * Event::on(
     * Customers::class,
     * Customers::EVENT_AFTER_SAVE_CUSTOMER,
     *     function(CustomerEvent $event) {
     *         // @var Customer $customer
     *         $customer = $event->customer;;
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_CUSTOMER = 'afterSaveCustomer';

    /**
     * @event CustomerAddressEvent The event that is triggered before customer address is saved.
     * @since 3.2.9
     *
     * ```php
     * Event::on(
     * Customers::class,
     * Customers::EVENT_BEFORE_SAVE_CUSTOMER_ADDRESS,
     *      function(CustomerAddressEvent $event) {
     *          // @var Customer $customer
     *          $customer = $event->customer;
     *
     *          // @var Address $address
     *          $address = $event->address;
     *      }
     * );
     * ```
     */
    const EVENT_BEFORE_SAVE_CUSTOMER_ADDRESS = 'beforeSaveCustomerAddress';

    /**
     * @event CustomerAddressEvent The event that is triggered after customer address is successfully saved.
     * @since 3.2.9
     *
     * ```php
     * Event::on(
     * Customers::class,
     * Customers::EVENT_AFTER_SAVE_CUSTOMER_ADDRESS,
     *      function(CustomerAddressEvent $event) {
     *          // @var Customer $customer
     *          $customer = $event->customer;
     *
     *          // @var Address $address
     *          $address = $event->address;
     *      }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_CUSTOMER_ADDRESS = 'afterSaveCustomerAddress';

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
    public function getCustomerById(int $id): ?Customer
    {
        $row = $this->_createCustomerQuery()
            ->where(['id' => $id])
            ->one();

        return $row ? new Customer($row) : null;
    }

    /**
     * Get the current customer by the current customer in session, or creates one if none exists.
     *
     * @return Customer
     */
    public function getCustomer(): Customer
    {
        $session = Craft::$app->getSession();
        $isNew = false;

        if ($this->_customer === null) {

            $user = Craft::$app->getUser()->getIdentity();

            // Can we get the current customer from the current user?
            if ($user) {
                $this->_customer = $this->getCustomerByUserId($user->id);

                if (!$this->_customer) {
                    $this->_customer = new Customer();
                    $this->_customer->userId = $user->id;
                    $isNew = true;
                }
            }

            // If we have no current user, can we get the current customer from the session (with no user logged in)
            if (!$user && ($session->getHasSessionId() || $session->getIsActive())) {
                if ($customerId = Craft::$app->getSession()->get(self::SESSION_CUSTOMER)) {
                    $this->_customer = $this->getCustomerById($customerId);
                }
            }

            // If we have no customer by now, just create one.
            if ($this->_customer === null) {
                $this->_customer = new Customer();
                $isNew = true;
            }
        }

        // Did we create a new customer? If so let's save it, so it has an ID.
        if ($isNew) {
            $this->saveCustomer($this->_customer);
        }

        // Store the customer in the session.
        Craft::$app->getSession()->set(self::SESSION_CUSTOMER, $this->_customer->id);

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
        // Fire a 'beforeSaveCustomerAddress' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_CUSTOMER_ADDRESS)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_CUSTOMER_ADDRESS, new CustomerAddressEvent([
                'address' => $address,
                'customer' => $customer
            ]));
        }

        // default to customer in session.
        if (null === $customer) {
            $customer = $this->getCustomer();
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
                // Fire a 'afterSaveCustomerAddress' event
                if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_CUSTOMER_ADDRESS)) {
                    $this->trigger(self::EVENT_AFTER_SAVE_CUSTOMER_ADDRESS, new CustomerAddressEvent([
                        'address' => $address,
                        'customer' => $customer
                    ]));
                }

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
        // Fire a 'beforeSaveCustomer' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_CUSTOMER)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_CUSTOMER, new CustomerEvent([
                'customer' => $customer
            ]));
        }

        if (!isset($customer->id)) {
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

        $customerRecord->setAttributes([
            'userId' => $customer->userId,
            'primaryBillingAddressId' => $customer->primaryBillingAddressId,
            'primaryShippingAddressId' => $customer->primaryShippingAddressId,
        ]);

        $customerRecord->validate();
        $customer->addErrors($customerRecord->getErrors());

        $customerRecord->save(false);
        $customer->id = $customerRecord->id;

        // Update the current customer if it was the one saved
        if ($this->_customer && $this->_customer->id == $customer->id) {
            $this->_customer = $customer;
        }

        // Fire a 'afterSaveCustomer' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_CUSTOMER)) {
            $this->trigger(self::EVENT_AFTER_SAVE_CUSTOMER, new CustomerEvent([
                'customer' => $customer
            ]));
        }

        return true;
    }

    /**
     * Get all address IDs for a customer by its ID.
     *
     * @param $customerId
     * @return array
     * @throws InvalidConfigException
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
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteCustomer(Customer $customer)
    {
        $customer = CustomerRecord::findOne($customer->id);

        if ($customer) {
            return $customer->delete();
        }

        return null;
    }

    /**
     * Deletes any customer record not related to a user or a cart.
     *
     * @since 2.2
     */
    public function purgeOrphanedCustomers(): void
    {
        $customers = (new Query())
            ->select(['[[customers.id]] id'])
            ->from(Table::CUSTOMERS . ' customers')
            ->leftJoin(Table::ORDERS . ' orders', '[[customers.id]] = [[orders.customerId]]')
            ->where(['[[orders.customerId]]' => null, '[[customers.userId]]' => null]);

        // Wrap subquery in another subquery to just select the ID. This is for MySQL compatibility.
        $customersIds = (new Query())
            ->select('custs.id')
            ->from(['custs' => $customers]);

        // This will also remove all addresses related to the customer.
        Craft::$app->getDb()->createCommand()
            ->delete(Table::CUSTOMERS, ['id' => $customersIds])
            ->execute();
    }

    /**
     * When a user logs in, consolidate all his/her orders.
     *
     * @param UserEvent $event
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws MissingComponentException
     */
    public function loginHandler(UserEvent $event): void
    {
        // Remove the old customer from the session.
        $this->forgetCustomer();

        $impersonating = Craft::$app->getSession()->get(UserElement::IMPERSONATE_KEY) !== null;
        // Don't allow transition of current cart to a user that is being impersonated.
        if ($impersonating) {
            Plugin::getInstance()->getCarts()->forgetCart();
        }

        Plugin::getInstance()->getCarts()->restorePreviousCartForCurrentUser();
    }

    /**
     * Forgets a Customer by deleting the customer from session and request.
     */
    public function forgetCustomer(): void
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
     * @throws Throwable
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
                    $order->setCustomer($toCustomer);

                    // We only want to update search indexes if the order is a cart and the developer wants to keep cart search indexes updated.
                    $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;
                    $updateSearchIndex = ($order->isCompleted || $updateCartSearchIndexes);

                    Craft::$app->getElements()->saveElement($order, false, false, $updateSearchIndex);
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
    public function getCustomerByUserId($id): ?Customer
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
     * @throws InvalidConfigException
     * @throws MissingComponentException
     */
    public function logoutHandler(UserEvent $event): void
    {
        // Reset the session's customer.
        Plugin::getInstance()->getCarts()->forgetCart();
        $this->forgetCustomer();
    }

    /**
     * Sets the last used addresses on the customer on order completion.
     *
     * Consolidates any other orders using the same email address.
     *
     * Duplicates the address records used for the order so they are independent to the
     * customers address book.
     *
     * @param Order $order
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     * @throws \yii\db\Exception
     */
    public function orderCompleteHandler(Order $order): void
    {
        // Create a user account if requested

        $this->_createUserFromOrder($order);

        // Consolidate orders for email address
        // This may change the customer on the order (which might drop the address ID's from the order), but
        // that is ok, since the _copyAddressesToOrder below will save the addresses.
        $this->consolidateGuestOrdersByEmail($order->email, $order);

        // Ensures that the completed order only has address IDs that belong ONLY to the order, and not an address book.
        $orderAddressesMutated = $this->_copyAddressesToOrder($order);

        if ($orderAddressesMutated) {
            // We don't need to update search indexes since the address contents are the same.
            Craft::$app->getElements()->saveElement($order, false, false, false);
        }

        // Copy address to guest customer's address book if they have no addresses
        $customer = $order->getCustomer();
        if ($customer && !$customer->userId && empty($customer->getAddresses()) && ($order->billingAddressId || $order->shippingAddressId)) {
            $addressesUpdated = false;
            if ($order->billingAddressId && $billingAddress = $order->getBillingAddress()) {
                $billingAddress->id = null;
                if ($this->saveAddress($billingAddress, $customer, false)) {
                    $customer->primaryBillingAddressId = $billingAddress->id;
                    $addressesUpdated = true;
                }
            }

            if ($order->shippingAddressId) {
                $shippingAddress = $order->getShippingAddress();
                if ($shippingAddress && $shippingAddress->sameAs($order->getBillingAddress())) {
                    // Don't create two addresses in the address book if they are the same
                    $customer->primaryShippingAddressId = $customer->primaryBillingAddressId;
                    $addressesUpdated = true;
                } else if ($shippingAddress) {
                    $shippingAddress->id = null;
                    if ($this->saveAddress($shippingAddress, $customer, false)) {
                        $customer->primaryShippingAddressId = $shippingAddress->id;
                        $addressesUpdated = true;
                    }
                }
            }

            if ($addressesUpdated) {
                $this->saveCustomer($customer);
            }
        }
    }

    /**
     * Retrieve customer query with the option to specify a search term
     *
     * @param string|null $search
     * @return Query
     * @since 3.1
     */
    public function getCustomersQuery(string $search = null): Query
    {
        $customersQuery = (new Query())
            ->select([
                'billing.address1 as billingAddress',
                'billing.firstName as billingFirstName',
                'billing.fullName as billingFullName',
                'billing.lastName as billingLastName',
                'customers.id as id',
                'email' => new Expression('CASE WHEN [[orders.email]] IS NULL THEN [[users.email]] ELSE [[orders.email]] END'),
                'primaryBillingAddressId',
                'primaryShippingAddressId',
                'shipping.address1 as shippingAddress',
                'shipping.firstName as shippingFirstName',
                'shipping.fullName as shippingFullName',
                'shipping.lastName as shippingLastName',
                'userId',
            ])
            ->from(Table::CUSTOMERS . ' customers')
            ->leftJoin(Table::ORDERS . ' orders', '[[orders.customerId]] = [[customers.id]]')
            ->leftJoin(CraftTable::USERS . ' users', '[[users.id]] = [[customers.userId]]')
            ->leftJoin(Table::ADDRESSES . ' billing', '[[billing.id]] = [[customers.primaryBillingAddressId]]')
            ->leftJoin(Table::ADDRESSES . ' shipping', '[[shipping.id]] = [[customers.primaryShippingAddressId]]')
            ->groupBy([
                'customers.id',
                'orders.email',
                'billing.firstName',
                'billing.lastName',
                'billing.fullName',
                'billing.address1',
                'shipping.firstName',
                'shipping.lastName',
                'shipping.fullName',
                'shipping.address1',
                'users.email',
            ])
            ->andWhere([
                'or',
                ['orders.isCompleted' => true],
                ['not', ['customers.userId' => null]]
            ]);

        if ($search) {
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
            $customersQuery->andWhere([
                'or',
                [$likeOperator, '[[billing.address1]]', $search],
                [$likeOperator, '[[billing.firstName]]', $search],
                [$likeOperator, '[[billing.fullName]]', $search],
                [$likeOperator, '[[billing.lastName]]', $search],
                [$likeOperator, '[[orders.email]]', $search],
                [$likeOperator, '[[orders.reference]]', $search],
                [$likeOperator, '[[orders.number]]', $search],
                [$likeOperator, '[[shipping.address1]]', $search],
                [$likeOperator, '[[shipping.firstName]]', $search],
                [$likeOperator, '[[shipping.fullName]]', $search],
                [$likeOperator, '[[shipping.lastName]]', $search],
                [$likeOperator, '[[users.username]]', $search],
                [$likeOperator, '[[users.firstName]]', $search],
                [$likeOperator, '[[users.lastName]]', $search],
                [$likeOperator, '[[users.email]]', $search],
            ]);
        }

        return $customersQuery;
    }

    /**
     * Consolidate all guest orders for this email address to use one customer record.
     *
     * @param string $email
     * @param Order|null $order
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     * @since 3.1.4
     */
    public function consolidateGuestOrdersByEmail(string $email, Order $order = null): void
    {
        // Consolidation customer for this email
        $customerId = null;

        // Try and find a customer related to a user with this email address
        if ($user = User::find()->email($email)->anyStatus()->one()) {
            if ($customer = $this->getCustomerByUserId($user->id)) {
                $customerId = $customer->id;
            }
        }

        // Try and find a customer past orders with this email address
        if (!$customerId) {
            $customerId = (new Query())
                ->select('orders.customerId')
                ->from(Table::ORDERS . ' orders')
                ->innerJoin(Table::CUSTOMERS . ' customers', '[[customers.id]] = [[orders.customerId]]')
                ->where(['orders.email' => $email])
                ->andWhere(['orders.isCompleted' => true])
                // we want the customers related to a userId to be listed first, then by their latest order
                ->orderBy('[[customers.userId]] DESC, [[orders.dateOrdered]] ASC')
                ->scalar();
        }

        // If we have no customer at this point, we have nothing to consolidate to
        if (!$customerId) {
            return;
        }

        // If we have a current order, lets update it with the consolidation customer now.
        if ($order && $order->customerId != $customerId) {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId);
            $order->setCustomer($customer);
        }

        // Get completed orders for other customers with the same email but not the same customer
        $orders = (new Query())
            ->select([
                'id' => 'orders.id',
                'userId' => 'customers.userId'
            ])
            ->where(['and', ['[[orders.email]]' => $email, '[[orders.isCompleted]]' => true], ['not', ['[[orders.customerId]]' => $customerId]]])
            ->leftJoin(Table::CUSTOMERS . ' customers', '[[orders.customerId]] = [[customers.id]]')
            ->from(Table::ORDERS . ' orders')
            ->all();

        foreach ($orders as $orderRow) {
            $orderId = $orderRow['id'];
            $userId = $orderRow['userId'];

            if (!$userId) {
                Craft::$app->getDb()->createCommand()
                    ->update(Table::ORDERS,
                        ['customerId' => $customerId],
                        ['id' => $orderId]
                    )->execute();
            }
        }
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

        if ($originalBillingAddress = $order->getBillingAddress()) {
            // Address ID could be null if the orders customer just got switched during order completion.
            // But that is OK, since we will mark order as mutated which will force the  order to save (which will also save the addresses).
            $originalBillingAddressId = $originalBillingAddress->id;
            $originalBillingAddress->id = null;
            if ($addressesService->saveAddress($originalBillingAddress, false)) {
                $mutated = true;
                $order->setBillingAddress($originalBillingAddress);
            } else {
                Craft::error(Craft::t('commerce', 'Unable to duplicate the billing address on order completion. Original billing address ID: {addressId}. Order ID: {orderId}',
                    ['addressId' => $originalBillingAddressId, 'orderId' => $order->id]), __METHOD__);
            }
        }

        if ($originalShippingAddress = $order->getShippingAddress()) {
            // Address ID could be null if the orders customer just got switched during order completion.
            // But that is OK, since we will mark order as mutated which will force the  order to save (which will also save the addresses).
            $originalShippingAddressId = $originalShippingAddress->id;
            $originalShippingAddress->id = null;
            if ($addressesService->saveAddress($originalShippingAddress, false)) {
                $mutated = true;
                $order->setShippingAddress($originalShippingAddress);
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
     * @throws Throwable
     * @throws ElementNotFoundException
     */
    private function _createUserFromOrder(Order $order): void
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
        $user = User::find()->email($order->email)->status(null)->one();
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

            // Saving a user *can* create a customer using $this->afterSaveUserHandler()
            $autoGeneratedCustomer = $this->getCustomerByUserId($user->id);
            // We dont want to have two customers with the same related user ID
            if ($autoGeneratedCustomer && $customer->id != $autoGeneratedCustomer->id) {
                $autoGeneratedCustomer->userId = null;
                $this->saveCustomer($autoGeneratedCustomer, false);
            }

            $customer->userId = $user->id;
            $this->saveCustomer($customer, false);
        } else {
            $errors = $user->getErrors();
            Craft::warning('Could not create user on order completion.', __METHOD__);
            Craft::warning($errors, __METHOD__);
        }
    }

    /**
     * @param array $context
     * @since 2.2
     */
    public function addEditUserCustomerInfoTab(array &$context): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$context['isNewUser'] && ($currentUser->can('commerce-manageOrders') || $currentUser->can('commerce-manageSubscriptions'))) {
            $context['tabs']['customerInfo'] = [
                'label' => Craft::t('commerce', 'Customer Info'),
                'url' => '#customerInfo'
            ];
        }
    }

    /**
     * Add customer info to the Edit User page in the CP
     *
     * @param array $context
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws InvalidConfigException
     * @since 2.2
     */
    public function addEditUserCustomerInfoTabContent(array &$context): string
    {
        if (!$context['user'] || $context['isNewUser']) {
            return '';
        }

        $customer = $this->getCustomerByUserId($context['user']->id);
        if (!$customer) {
            return '';
        }

        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);
        return Craft::$app->getView()->renderTemplate('commerce/customers/_includes/_editUserTab', [
            'customer' => $customer,
            'addressRedirect' => $context['user']->getCpEditUrl() . '#customerInfo',
        ]);
    }

    /**
     * @param ModelEvent $event
     * @throws Exception
     */
    public function afterSaveUserHandler(ModelEvent $event): void
    {
        $user = $event->sender;
        $customer = $this->getCustomerByUserId($user->id);
        $email = $user->email;

        // Create a new customer for a user that does not have a customer
        if (!$customer && $user->email) {
            $existingCustomerIdByEmail = (new Query())
                ->select('orders.customerId')
                ->from(Table::ORDERS . ' orders')
                ->innerJoin(Table::CUSTOMERS . ' customers', '[[customers.id]] = [[orders.customerId]]')
                ->where(['orders.email' => $user->email])
                ->andWhere(['orders.isCompleted' => true])
                ->orderBy('[[orders.dateOrdered]] ASC')
                ->scalar(); // get the first customerId in the result

            if ($customer = $this->getCustomerById($existingCustomerIdByEmail)) {
                $customer->userId = $user->id;
            } else {
                $customer = new Customer(['userId' => $user->id]);
            }

            $this->saveCustomer($customer);
        }

        // Update the email address in the DB for this customer
        if ($email) {
            $this->_updatePreviousOrderEmails($customer->id, $email);
        }

        $this->consolidateGuestOrdersByEmail($email);
    }

    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.2.0
     */
    public function eagerLoadCustomerForOrders(array $orders): array
    {
        $customerIds = ArrayHelper::getColumn($orders, 'customerId');
        $customersResults = $this->_createCustomerQuery()
            ->andWhere(['id' => $customerIds])
            ->andWhere(['not', ['userId' => null]])
            ->all();

        $customers = [];
        $userIds = ArrayHelper::getColumn($customersResults, 'userId');
        $users = User::find()->id($userIds)->limit(null)->all();

        foreach ($customersResults as $result) {
            $customer = new Customer($result);

            // also eager load the user on the customer if possible
            if ($customer->userId && $user = ArrayHelper::firstWhere($users, 'id', $customer->userId)) {
                $customer->setUser($user);
            }

            $customers[$customer->id] = $customers[$customer->id] ?? $customer;
        }

        foreach ($orders as $key => $order) {
            if (isset($customers[$order->customerId])) {
                $order->setCustomer($customers[$order->customerId]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    /**
     * @param int $customerId
     * @param string $email
     * @throws \yii\db\Exception
     */
    private function _updatePreviousOrderEmails(int $customerId, string $email): void
    {
        $orderIds = (new Query())
            ->select(['orders.id'])
            ->from([Table::ORDERS . ' orders'])
            ->where(['orders.customerId' => $customerId])
            ->andWhere(['not', ['orders.email' => $email]])
            ->column();

        if (!empty($orderIds)) {
            Craft::$app->getDb()->createCommand()
                ->update(Table::ORDERS, ['email' => $email], ['id' => $orderIds])
                ->execute();
        }
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
                'dateCreated',
                'dateUpdated',
                'id',
                'primaryBillingAddressId',
                'primaryShippingAddressId',
                'userId',
            ])
            ->from([Table::CUSTOMERS]);
    }
}
