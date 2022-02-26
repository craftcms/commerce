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
use craft\events\ModelEvent;
use craft\events\UserEvent;
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
     * Handle user save
     *
     * @param ModelEvent $event
     * @return void
     */
    public function afterSaveUserHandler(ModelEvent $event)
    {
        /** @var User|CustomerBehavior $user */
        $user = $event->sender;

        if ($user->primaryBillingAddressId) {
            $this->savePrimaryBillingAddressId($user, $user->primaryBillingAddressId);
        }

        if ($user->primaryShippingAddressId) {
            $this->savePrimaryShippingAddressId($user, $user->primaryShippingAddressId);
        }
    }

    /**
     * @param User $user
     * @param int $addressId
     * @return bool
     */
    public function savePrimaryShippingAddressId(User $user, int $addressId): bool
    {
        $userRecord = CustomerRecord::findOne($user->id) ?: $this->_createCustomerRecord($user);
        $userRecord->primaryShippingAddressId = $addressId;
        /** @var User|CustomerBehavior $user */
        $user->primaryShippingAddressId = $addressId;
        return $userRecord->save();
    }

    /**
     * @param User $user
     * @param int $addressId
     * @return bool
     */
    public function savePrimaryBillingAddressId(User $user, int $addressId): bool
    {
        $userRecord = CustomerRecord::findOne($user->id) ?: $this->_createCustomerRecord($user);
        $userRecord->primaryBillingAddressId = $addressId;
        /** @var User|CustomerBehavior $user */
        $user->primaryBillingAddressId = $addressId;
        return $userRecord->save();
    }

    /**
     * Handle user login
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
    public function addEditUserCommerceTabContent(array &$context): string
    {
        if (!$context['user'] || $context['isNewUser']) {
            return '';
        }

        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);
        return Craft::$app->getView()->renderTemplate('commerce/_includes/users/_editUserTab', [
            'user' => $context['user']
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
            if (isset($users[$order->customerId])) {
                $order->setCustomer($users[$order->customerId]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    /**
     * Makes sure the user has an email address and sets them to pending and sends the activation email
     */
    private function _activateUserFromOrder(Order $order): void
    {
        // Only if on pro edition
        if (Craft::$app->getEdition() != Craft::Pro) {
            return;
        }

        $user = $order->getCustomer();

        // can't create a user without an email
        if (!$user || !$user->email) {
            return;
        }

        // Create a new user
        $user->firstName = $order->billingAddress?->firstName;
        $user->lastName = $order->billingAddress?->lastName;
        $user->pending = true;

        $user->setScenario(Element::SCENARIO_ESSENTIALS);

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
     * @param User $user
     * @return CustomerRecord
     */
    private function _createCustomerRecord(User $user): CustomerRecord
    {
        $customer = new CustomerRecord();
        $customer->id = $user->id;
        $customer->save();
        return $customer;
    }
}