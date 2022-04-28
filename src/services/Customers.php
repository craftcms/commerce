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
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Customer as CustomerRecord;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\elements\User;
use craft\helpers\ArrayHelper;

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
     * Add customer info tab to the Edit User page in the CP
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
     * Add customer info to the Edit User page in the CP
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
        $customerRecord = CustomerRecord::find()->where(['customerId' => $user->id])->one();
        if (!$customerRecord) {
            $customerRecord = new CustomerRecord();
            $customerRecord->customerId = $user->id;
            $customerRecord->save();
        }

        return $customerRecord;
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
