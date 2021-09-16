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
use craft\commerce\events\UserAddressEvent;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\commerce\records\UserAddress;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\events\UserEvent;
use craft\helpers\ArrayHelper;
use Throwable;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * Users service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Users extends Component
{
    /**
     * @event UserAddressEvent The event that is triggered before user address is saved.
     *
     * ```php
     * Event::on(
     * Customers::class,
     * Customers::EVENT_BEFORE_SAVE_USER_ADDRESS,
     *      function(UserAddressEvent $event) {
     *          // @var User $user
     *          $user = $event->user;
     *
     *          // @var Address $address
     *          $address = $event->address;
     *      }
     * );
     * ```
     */
    const EVENT_BEFORE_SAVE_USER_ADDRESS = 'beforeSaveUserAddress';

    /**
     * @event UserAddressEvent The event that is triggered after user address is successfully saved.
     *
     * ```php
     * Event::on(
     * Customers::class,
     * Customers::EVENT_AFTER_SAVE_USER_ADDRESS,
     *      function(UserAddressEvent $event) {
     *          // @var Customer $user
     *          $user = $event->user;
     *
     *          // @var Address $address
     *          $address = $event->address;
     *      }
     * );
     * ```
     */
    const EVENT_AFTER_SAVE_USER_ADDRESS = 'afterSaveUserAddress';

    /**
     * @param Order[] $orders
     * @return array
     */
    public function eagerLoadUsersForOrders(array $orders): array
    {
        $userIds = ArrayHelper::getColumn($orders, 'userId');
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
            if (isset($users[$order->userId])) {
                $order->setUser($users[$order->userId]);
                $orders[$key] = $order;
            }
        }

        return $orders;
    }

    /**
     * Associates an address with the user, and saves the address.
     *
     * @param Address $address
     * @param User $user Defaults to the current customer in session if none is passing in.
     * @param bool $runValidation should we validate this address before saving.
     * @param bool|null $isPrimaryBillingAddress
     * @param bool|null $isPrimaryShippingAddress
     * @return bool
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function saveAddress(Address $address, User $user, bool $runValidation = true, ?bool $isPrimaryBillingAddress = null, ?bool $isPrimaryShippingAddress = null): bool
    {
        // Fire a 'beforeSaveUserAddress' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_USER_ADDRESS)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_USER_ADDRESS, new UserAddressEvent([
                'address' => $address,
                'user' => $user,
            ]));
        }

        if (Plugin::getInstance()->getAddresses()->saveAddress($address, $runValidation)) {
            $userAddressRecord = UserAddress::find()->where([
                'userId' => $user->id,
                'addressId' => $address->id,
            ])->one();

            if (!$userAddressRecord) {
                $userAddressRecord = new UserAddress();
            }

            $userAddressRecord->userId = $user->id;
            $userAddressRecord->addressId = $address->id;

            // Set primary billing and shipping if we are being explicit asked to
            if (null !== $isPrimaryBillingAddress) {
                $userAddressRecord->isPrimaryBillingAddress = $isPrimaryBillingAddress;
            }

            if (null !== $isPrimaryShippingAddress) {
                $userAddressRecord->isPrimaryShippingAddress = $isPrimaryShippingAddress;
            }

            if ($userAddressRecord->save()) {
                // Fire a 'afterSaveUserAddress' event
                if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_USER_ADDRESS)) {
                    $this->trigger(self::EVENT_AFTER_SAVE_USER_ADDRESS, new UserAddressEvent([
                        'address' => $address,
                        'user' => $user,
                    ]));
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @param User $user
     * @return int[]
     */
    public function getUserGroupIdsByUser(User $user): array
    {
        $userGroups = $user->getGroups() ?? [];
        return ArrayHelper::getColumn($userGroups, 'id');
    }

    /**
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws \yii\base\Exception
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
     * @param Order $order
     * @throws ElementNotFoundException
     * @throws Throwable
     * @throws \yii\base\Exception
     */
    public function orderCompleteHandler(Order $order): void
    {
        // Only do this if requested
        if (!$order->registerUserOnOrderComplete) {
            return;
        }

        // Only if on pro edition
        if (Craft::$app->getEdition() != Craft::Pro) {
            return;
        }

        $user = $order->getUser();
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
     * @since 2.2
     */
    public function addEditUserCommerceTabContent(array &$context): string
    {
        if (!$context['user'] || $context['isNewUser']) {
            return '';
        }

        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);
        return Craft::$app->getView()->renderTemplate('commerce/customers/_includes/_editUserTab', [
            'user' => $context['user'],
            'addressRedirect' => $context['user']->getCpEditUrl() . '#commerce',
        ]);
    }

}