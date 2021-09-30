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
use craft\commerce\models\Customer;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\db\Query;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
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
    /**
     * Get all customers.
     *
     * @return User[]
     */
    public function getAllCustomers(): array
    {
        $ids = (new Query())->from(Table::USERS)
            ->select(['userId'])
            ->column();

        if (empty($ids)) {
            return [];
        }

        return User::find()->id($ids)->all();
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
        $impersonating = Craft::$app->getSession()->get(User::IMPERSONATE_KEY) !== null;
        // Don't allow transition of current cart to a user that is being impersonated.
        if ($impersonating) {
            Plugin::getInstance()->getCarts()->forgetCart();
        }

        Plugin::getInstance()->getCarts()->restorePreviousCartForCurrentUser();
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

        // Ensures that the completed order only has address IDs that belong ONLY to the order, and not an address book.
        $orderAddressesMutated = $this->_copyAddressesToOrder($order);

        if ($orderAddressesMutated) {
            // We don't need to update search indexes since the address contents are the same.
            Craft::$app->getElements()->saveElement($order, false, false, false);
        }

        // Copy address to guest customer's address book if they have no addresses
        $customer = $order->getCustomer();
        // if ($customer && !$customer->userId && empty($customer->getAddresses()) && ($order->billingAddressId || $order->shippingAddressId)) {
        //     $addressesUpdated = false;
        //     if ($order->billingAddressId && $billingAddress = $order->getBillingAddress()) {
        //         $billingAddress->id = null;
        //         if ($this->saveAddress($billingAddress, $customer, false)) {
        //             $customer->primaryBillingAddressId = $billingAddress->id;
        //             $addressesUpdated = true;
        //         }
        //     }
        //
        //     if ($order->shippingAddressId) {
        //         $shippingAddress = $order->getShippingAddress();
        //         if ($shippingAddress && $shippingAddress->sameAs($order->getBillingAddress())) {
        //             // Don't create two addresses in the address book if they are the same
        //             $customer->primaryShippingAddressId = $customer->primaryBillingAddressId;
        //             $addressesUpdated = true;
        //         } else if ($shippingAddress) {
        //             $shippingAddress->id = null;
        //             if ($this->saveAddress($shippingAddress, $customer, false)) {
        //                 $customer->primaryShippingAddressId = $shippingAddress->id;
        //                 $addressesUpdated = true;
        //             }
        //         }
        //     }
        //
        //     if ($addressesUpdated) {
        //         $this->saveCustomer($customer);
        //     }
        // }
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

        $user = $order->getCustomer();
        if (!$user) {
            return;
        }

        // can't create a user without an email
        if (!$user->email) {
            return;
        }

        // User is already registered
        if ($user->getIsCredentialed()) {
            return;
        }

        $user->unverifiedEmail = $user->email;
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
        } else {
            $errors = $user->getErrors();
            Craft::warning('Could not create user on order completion.', __METHOD__);
            Craft::warning($errors, __METHOD__);
        }
    }

    /**
     * @param array $context
     * @since 4.0
     */
    public function addEditUserCommerceTab(array &$context): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$context['isNewUser'] && ($currentUser->can('commerce-manageOrders') || $currentUser->can('commerce-manageSubscriptions'))) {
            $context['tabs']['commerce'] = [
                'label' => Craft::t('commerce', 'Commerce'),
                'url' => '#commerce',
            ];
        }
    }

    /**
     * Add commerce info to the Edit User page in the CP
     *
     * @param array $context
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws InvalidConfigException
     * @since 4.0
     */
    public function addEditUserCommerceTabContent(array &$context): string
    {
        if (!$context['user'] || $context['isNewUser']) {
            return '';
        }

        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);
        return Craft::$app->getView()->renderTemplate('commerce/_includes/users/editUserTab', [
            'user' => $context['user'],
            'addressRedirect' => $context['user']->getCpEditUrl() . '#commerce',
        ]);
    }

    /**
     * @param array|Order[] $orders
     * @return Order[]
     * @since 3.2.0
     */
    public function eagerLoadCustomerForOrders(array $orders): array
    {
        $userIds = ArrayHelper::getColumn($orders, 'customerId');
        $userIds = array_filter($userIds);

        if (empty($userIds)) {
            return $orders;
        }

        $users = User::find()
            ->id($userIds)
            ->limit(null)
            ->indexBy('id')
            ->all();

        foreach ($orders as $key => $order) {
            if (isset($users[$order->customerId])) {
                $order->setCustomer($users[$order->customerId]);
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
