<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\commerce\behaviors\CustomerBehavior;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\UpdatePrimaryPaymentSourceEvent;
use craft\commerce\Plugin;
use craft\commerce\records\Customer as CustomerRecord;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\db\Query;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\errors\UnsupportedSiteException;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use yii\db\Expression;

/**
 * Customer service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Customers extends Component
{
    // Events
    // -------------------------------------------------------------------------

    /**
     * @event RegisterElementSourcesEvent The event that is triggered when a primary payment method is saved.
     */
    public const EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE = 'updatePrimaryPaymentSource';

    /**
     * @param User $user
     * @param int|null $addressId
     * @return bool
     */
    public function savePrimaryShippingAddressId(User $user, ?int $addressId): bool
    {
        $customerRecord = $this->ensureCustomer($user);
        $customerRecord->primaryShippingAddressId = $addressId;
        /** @var User|CustomerBehavior $user */
        $user->primaryShippingAddressId = $addressId;
        return $customerRecord->save();
    }

    /**
     * @param User $user
     * @param int|null $addressId
     * @return bool
     */
    public function savePrimaryBillingAddressId(User $user, ?int $addressId): bool
    {
        $customerRecord = $this->ensureCustomer($user);
        $customerRecord->primaryBillingAddressId = $addressId;
        /** @var User|CustomerBehavior $user */
        $user->primaryBillingAddressId = $addressId;
        return $customerRecord->save();
    }

    /**
     * @param User $user
     * @param int|null $paymentSourceId
     * @return bool
     * @since 4.2
     */
    public function savePrimaryPaymentSourceId(User $user, ?int $paymentSourceId): bool
    {
        $customerRecord = $this->ensureCustomer($user);

        $originalPaymentSourceId = $customerRecord->primaryPaymentSourceId;

        // Only save customer record if the source is not already primary
        if ($customerRecord->primaryPaymentSourceId == $paymentSourceId) {
            return true;
        }

        $customerRecord->primaryPaymentSourceId = $paymentSourceId;

        if (!$customerRecord->save()) {
            return false;
        }

        /** @var User|CustomerBehavior $user */
        $user->primaryPaymentSourceId = $paymentSourceId;

        if ($originalPaymentSourceId != $paymentSourceId) {
            $event = new UpdatePrimaryPaymentSourceEvent([
                'previousPrimaryPaymentSourceId' => $originalPaymentSourceId,
                'newPrimaryPaymentSourceId' => $paymentSourceId,
                'customer' => $user,
            ]);

            // trigger the update primary payment source event
            $this->trigger(self::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE, $event);
        }

        return true;
    }

    /**
     * Handle user login
     */
    public function loginHandler(): void
    {
        $impersonating = Craft::$app->getSession()->get(User::IMPERSONATE_KEY) !== null;
        // Don't allow transition of current cart to a user that is being impersonated.
        if ($impersonating) {
            Plugin::getInstance()->getCarts()->forgetCart();
        }

        Plugin::getInstance()->getCarts()->restorePreviousCartForCurrentUser();
    }

    /**
     * Add customer info tab to the Edit User page in the control panel.
     */
    public function addEditUserCommerceTab(array &$context): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$context['isNewUser'] && ($currentUser->can('commerce-manageOrders') || $currentUser->can('commerce-manageSubscriptions'))) {
            $context['tabs']['customerInfo'] = [
                'label' => Craft::t('commerce', 'Commerce'),
                'url' => '#commerce',
            ];
        }
    }

    /**
     * Add customer info to the Edit User page in the control panel.
     */
    public function addEditUserCommerceTabContent(array $context): string
    {
        if (!$context['user'] || $context['isNewUser']) {
            return '';
        }

        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);
        return Craft::$app->getView()->renderTemplate('commerce/_includes/users/_editUserTab', [
            'user' => $context['user'],
        ]);
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
     */
    public function orderCompleteHandler(Order $order): void
    {
        // Create a user account if requested
        if ($order->registerUserOnOrderComplete) {
            $this->_activateUserFromOrder($order);
        }

        if ($order->saveBillingAddressOnOrderComplete || $order->saveShippingAddressOnOrderComplete) {
            $this->_saveAddressesFromOrder($order);
        }
    }

    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.2.0
     */
    public function eagerLoadCustomerForOrders(array $orders): array
    {
        $customerIds = ArrayHelper::getColumn($orders, 'customerId');
        /** @var User[] $users */
        $users = User::find()->id($customerIds)->limit(null)->indexBy('id')->all();

        foreach ($orders as $key => $order) {
            $customerId = $order->getCustomerId();
            if (isset($users[$customerId])) {
                $order->setCustomer($users[$customerId]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    /**
     * Returns a customer record by a user element, creating one if none already exists.
     *
     * @param User $user
     * @return CustomerRecord
     */
    public function ensureCustomer(User $user): CustomerRecord
    {
        /** @var CustomerRecord|null $customerRecord */
        $customerRecord = CustomerRecord::find()->where(['customerId' => $user->id])->one();
        if (!$customerRecord) {
            $customerRecord = new CustomerRecord();
            $customerRecord->customerId = $user->id;
            $customerRecord->save();
        }

        return $customerRecord;
    }

    /**
     * @return bool Whether the data moved successfully
     * @throws ElementNotFoundException|\yii\db\Exception
     * @since 4.1.0
     */
    public function transferCustomerData(User $fromCustomer, User $toCustomer): bool
    {
        $fromId = $fromCustomer->id;
        $toId = $toCustomer->id;

        /** @var User|null $fromUser */
        $fromUser = User::find()->id($fromId)->one();
        /** @var User|null $toUser */
        $toUser = User::find()->id($toId)->one();

        if ($fromUser === null) {
            throw new ElementNotFoundException('User ID:', $fromId);
        }

        if ($toUser === null) {
            throw new ElementNotFoundException('User ID:', $toId);
        }

        $userRefs = [
            Table::ORDERHISTORIES => 'userId',
            Table::SUBSCRIPTIONS => 'userId',
            Table::TRANSACTIONS => 'userId',
            Table::ORDERS => 'customerId',
            Table::PAYMENTSOURCES => 'customerId',
        ];

        foreach ($userRefs as $table => $column) {
            Db::update($table, [
                $column => $toId,
            ], [
                $column => $fromId,
            ], [], false);
        }

        $previousUses = (new Query())->select(['discountId', 'uses'])->from(Table::CUSTOMER_DISCOUNTUSES)->where(['customerId' => $fromId])->pairs();
        $toUses = (new Query())->select(['discountId', 'uses'])->from(Table::CUSTOMER_DISCOUNTUSES)->where(['customerId' => $toId])->pairs();

        foreach ($previousUses as $discountId => $uses) {
            if (isset($toUses[$discountId])) {
                Db::update(
                    table: Table::CUSTOMER_DISCOUNTUSES,
                    columns: ['uses' => new Expression("uses + $uses")],
                    condition: [
                        'customerId' => $toId,
                        'discountId' => $discountId,
                    ],
                    params: [],
                    updateTimestamp: false
                );
            } else {
                Db::insert(
                    table: Table::CUSTOMER_DISCOUNTUSES,
                    columns: [
                        'uses' => $uses,
                        'customerId' => $toId,
                        'discountId' => $discountId,
                    ]
                );
            }

            // Remove uses from fromCustomer
            Db::update(
                table: Table::CUSTOMER_DISCOUNTUSES,
                columns: ['uses' => 0],
                condition: [
                    'customerId' => $fromId,
                    'discountId' => $discountId,
                ],
                params: [],
                updateTimestamp: false
            );
        }


        $fromEmail = $fromUser->email;
        $toEmail = $toUser->email;

        $emailRefs = [
            Table::ORDERS => 'email',
        ];

        foreach ($emailRefs as $table => $column) {
            Db::update($table, [
                $column => $toEmail,
            ], [
                $column => $fromEmail,
            ], [], false);
        }

        return true;
    }

    /**
     * @param Order $order
     * @return void
     * @throws \Throwable
     * @throws InvalidElementException
     * @throws UnsupportedSiteException
     */
    private function _saveAddressesFromOrder(Order $order): void
    {
        // Only for completed orders
        if ($order->isCompleted === false) {
            return;
        }

        // Check for a credentialed user
        if ($order->getCustomer() === null || !$order->getCustomer()->getIsCredentialed()) {
            return;
        }

        $saveBillingAddress = $order->saveBillingAddressOnOrderComplete && $order->sourceBillingAddressId === null && $order->billingAddressId;
        $saveShippingAddress = $order->saveShippingAddressOnOrderComplete && $order->sourceShippingAddressId === null && $order->shippingAddressId;
        $newSourceBillingAddressId = null;
        $newSourceShippingAddressId = null;

        if ($saveBillingAddress && $saveShippingAddress && $order->hasMatchingAddresses()) {
            // Only save one address if they are matching
            $newAddress = Craft::$app->getElements()->duplicateElement($order->getBillingAddress(),
                [
                    'owner' => $order->getCustomer(),
                ]
            );
            $newSourceBillingAddressId = $newAddress->id;
            $newSourceShippingAddressId = $newAddress->id;
        } else {
            if ($saveBillingAddress) {
                $newBillingAddress = Craft::$app->getElements()->duplicateElement($order->getBillingAddress(),
                    [
                        'owner' => $order->getCustomer(),
                    ]);
                $newSourceBillingAddressId = $newBillingAddress->id;
            }

            if ($saveShippingAddress) {
                $newShippingAddress = Craft::$app->getElements()->duplicateElement($order->getShippingAddress(), [
                    'owner' => $order->getCustomer(),
                ]);
                $newSourceShippingAddressId = $newShippingAddress->id;
            }
        }

        if ($newSourceBillingAddressId) {
            $order->sourceBillingAddressId = $newSourceBillingAddressId;
        }

        if ($newSourceShippingAddressId) {
            $order->sourceShippingAddressId = $newSourceShippingAddressId;
        }

        // Manually update the order DB record to avoid looped element saves
        if ($newSourceBillingAddressId || $newSourceShippingAddressId) {
            \craft\commerce\records\Order::updateAll([
                'sourceBillingAddressId' => $order->sourceBillingAddressId,
                'sourceShippingAddressId' => $order->sourceShippingAddressId,
            ],
                [
                    'id' => $order->id,
                ]
            );
        }
    }

    /**
     * Makes sure the user has an email address and sets them to pending and sends the activation email
     */
    private function _activateUserFromOrder(Order $order): void
    {
        $user = $order->getCustomer();
        if (!$user || $user->active || $user->locked || $user->suspended) {
            return;
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        if (!$user->fullName) {
            $user->fullName = $billingAddress?->fullName ?? $shippingAddress?->fullName ?? '';
        }

        $user->username = $order->getEmail();
        $user->pending = true;
        $user->setScenario(Element::SCENARIO_ESSENTIALS);

        if (Craft::$app->getElements()->saveElement($user)) {
            Craft::$app->getUsers()->assignUserToDefaultGroup($user);
            $emailSent = Craft::$app->getUsers()->sendActivationEmail($user);

            if (!$emailSent) {
                Craft::warning('"registerUserOnOrderComplete" used to create the user, but couldn’t send an activation email. Check your email settings.', __METHOD__);
            }

            if ($billingAddress || $shippingAddress) {
                $newAttributes = [
                    'owner' => $user
                ];

                // If there is only one address make sure we don't add duplicates to the user
                if ($order->hasMatchingAddresses()) {
                    $newAttributes['title'] = Craft::t('app', 'Address');
                    $shippingAddress = null;
                }

                // Copy addresses to user
                if ($billingAddress) {
                    $newBillingAddress = Craft::$app->getElements()->duplicateElement($billingAddress, $newAttributes);

                    /**
                     * Because we are cloning from an order address the `CustomerAddressBehavior` hasn't been instantiated
                     * therefore we are unable to simply set the `isPrimaryBilling` property when specifying the new attributes during duplication.
                     */
                    if (!$newBillingAddress->hasErrors()) {
                        $this->savePrimaryBillingAddressId($user, $newBillingAddress->id);

                        if ($order->hasMatchingAddresses()) {
                            $this->savePrimaryShippingAddressId($user, $newBillingAddress->id);
                        }
                    }
                }

                if ($shippingAddress) {
                    $newShippingAddress = Craft::$app->getElements()->duplicateElement($shippingAddress, $newAttributes);

                    /**
                     * Because we are cloning from an order address the `CustomerAddressBehavior` hasn't been instantiated
                     * therefore we are unable to simply set the `isPrimaryShipping` property when specifying the new attributes during duplication.
                     */
                    if (!$newShippingAddress->hasErrors()) {
                        $this->savePrimaryShippingAddressId($user, $newShippingAddress->id);
                    }
                }
            }
        } else {
            $errors = $user->getErrors();
            Craft::warning('Could not create user on order completion.', __METHOD__);
            Craft::warning($errors, __METHOD__);
        }
    }
}
