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
use craft\commerce\Plugin;
use craft\commerce\records\Customer as CustomerRecord;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\db\Query;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
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
        $customerRecord->primaryPaymentSourceId = $paymentSourceId;

        if (!$customerRecord->save()) {
            return false;
        }

        /** @var User|CustomerBehavior $user */
        $user->primaryPaymentSourceId = $paymentSourceId;
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
     * Makes sure the user has an email address and sets them to pending and sends the activation email
     */
    private function _activateUserFromOrder(Order $order): void
    {
        if (!$order->email) {
            return;
        }

        $user = Craft::$app->getUsers()->ensureUserByEmail($order->email);

        if (!$user->getIsCredentialed()) {
            if (!$user->fullName) {
                $user->fullName = $order->getBillingAddress()?->fullName ?? $order->getShippingAddress()?->fullName ?? '';
            }

            $user->username = $order->email;
            $user->pending = true;
            $user->setScenario(Element::SCENARIO_ESSENTIALS);

            if (Craft::$app->getElements()->saveElement($user)) {
                Craft::$app->getUsers()->assignUserToDefaultGroup($user);
                $emailSent = Craft::$app->getUsers()->sendActivationEmail($user);

                if (!$emailSent) {
                    Craft::warning('"registerUserOnOrderComplete" used to create the user, but couldn’t send an activation email. Check your email settings.', __METHOD__);
                }
            } else {
                $errors = $user->getErrors();
                Craft::warning('Could not create user on order completion.', __METHOD__);
                Craft::warning($errors, __METHOD__);
            }
        }
    }
}
