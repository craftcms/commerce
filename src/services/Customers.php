<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\Plugin;
use craft\commerce\records\Customer as CustomerRecord;
use craft\commerce\records\CustomerAddress as CustomerAddressRecord;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Event;
use yii\base\Exception;
use yii\web\UserEvent;

/**
 * Customer service.
 *
 * @property mixed            $lastUsedAddresses
 * @property array|Customer[] $allCustomers
 * @property Customer         $customer
 * @property int              $customerId   id of current customer record
 * @property Customer         $savedCustomer
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Customers extends Component
{
    // Constants
    // =========================================================================

    const SESSION_CUSTOMER = 'commerce_customer_cookie';

    // Properties
    // =========================================================================

    /**
     * @var Customer
     */
    private $_customer;

    // Public Methods
    // =========================================================================

    /**
     *
     * @return Customer[]
     */
    public function getAllCustomers(): array
    {
        $records = CustomerRecord::find()->all();

        return ArrayHelper::map($records, 'id', function($item) {
            return $this->_createCustomerFromCustomerRecord($item);
        });
    }

    /**
     * @param int $id
     *
     * @return Customer|null
     */
    public function getCustomerById(int $id)
    {
        $result = CustomerRecord::findOne($id);

        if ($result) {
            return $this->_createCustomerFromCustomerRecord($result);
        }

        return null;
    }

    /**
     * @return bool
     */
    public function isCustomerSaved(): bool
    {
        return (bool)$this->getCustomer()->id;
    }

    /**
     * Must always return a customer
     *
     * @return Customer
     */
    public function getCustomer(): Customer
    {
        if ($this->_customer === null) {
            $user = Craft::$app->getUser()->getIdentity();

            // Find user's customer or the current customer in the session.
            if ($user) {
                $record = CustomerRecord::find()->where(['userId' => $user->id])->one();

                if ($record) {
                    Craft::$app->getSession()->set(self::SESSION_CUSTOMER, $record->id);
                }
            } else {
                $id = Craft::$app->getSession()->get(self::SESSION_CUSTOMER);
                if ($id) {
                    $record = CustomerRecord::findOne($id);

                    // If there is a customer record but it is associated with a real user, don't use it when guest.
                    if ($record && $record['userId']) {
                        $record = null;
                    }
                }
            }

            if (empty($record)) {
                $record = new CustomerRecord();

                if ($user) {
                    $record->userId = $user->id;
                }
            }

            $this->_customer = $this->_createCustomerFromCustomerRecord($record);
        }

        return $this->_customer;
    }

    /**
     * Associates an address with the saved customer, and saves the address.
     *
     * @param Address $address
     *
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
     * @param Customer $customer
     *
     * @return bool
     * @throws Exception
     */
    public function saveCustomer(Customer $customer): bool
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
     * @param Customer $customer
     *
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
     * @param UserEvent $event
     */
    public function loginHandler(UserEvent $event)
    {
        // Remove the customer from session.
        $this->forgetCustomer();
        $username = $event->identity->username;
        $this->consolidateOrdersToUser($username);
    }

    /**
     * Forgets a Customer by deleting the customer from session and request.
     */
    public function forgetCustomer()
    {
        $this->_customer = null;
        Craft::$app->getSession()->remove(self::SESSION_CUSTOMER);
    }

    /**
     * @param string $username
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function consolidateOrdersToUser($username): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            /** @var User $user */
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($username);

            if (!$user) {
                return false;
            }

            $toCustomer = $this->getCustomerByUserId($user->id);

            // The user has no previous customer record, create one.
            if (!$toCustomer) {
                $toCustomer = new Customer();
                $toCustomer->setUser($user);
                if (!$this->saveCustomer($toCustomer)) {
                    return false;
                }
            }

            // Shouldn't really happen as all users should have an email.
            if (!$toCustomer->email) {
                return false;
            }

            // Grab all the orders for the customer.
            $orders = Plugin::getInstance()->getOrders()->getOrdersByEmail($toCustomer->email);

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

            return true;
        } catch (\Exception $e) {
            Craft::error('Could not consolidate orders to username: '.$username.'. Reason: '.$e->getMessage(), __METHOD__);
            $transaction->rollBack();
        }

        return false;
    }

    /**
     * @param $id
     *
     * @return Customer|null
     */
    public function getCustomerByUserId($id)
    {
        $result = CustomerRecord::find()->where(['userId' => $id])->one();

        if ($result) {
            return $this->_createCustomerFromCustomerRecord($result);
        }

        return null;
    }

    /**
     * Returns the user groups of the user param but defaults to the current user
     *
     * @param User|null $user
     *
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
     * @param UserEvent $event
     *
     * @throws Exception
     */
    public function logoutHandler(UserEvent $event)
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
        if (!Craft::$app->request->isConsoleRequest) {
            if ($order->customerId == $this->getCustomerId()) {
                $this->setLastUsedAddresses($order->billingAddressId, $order->shippingAddressId);
            }
        }

        // Now duplicate the addresses on the order
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
            if (Plugin::getInstance()->getAddresses()->saveAddress($snapShotBillingAddress, false)) {
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
            if (Plugin::getInstance()->getAddresses()->saveAddress($snapShotShippingAddress, false)) {
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
     * @param $billingId
     * @param $shippingId
     *
     * @return bool
     * @throws Exception
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
     * @param Event $event
     *
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
     * @return Customer
     * @throws Exception
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
     * Creates a Customer with attributes from a CustomerRecord.
     *
     * @param CustomerRecord|null $record
     *
     * @return Customer|null
     */
    private function _createCustomerFromCustomerRecord(CustomerRecord $record = null)
    {
        if (!$record) {
            return null;
        }

        return new Customer($record->toArray([
            'id',
            'userId',
            'lastUsedBillingAddressId',
            'lastUsedShippingAddressId'
        ]));
    }
}
