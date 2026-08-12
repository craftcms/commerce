<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Customer as CustomerRecord;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\Element\Queries\Exceptions\ElementNotFoundException;
use CraftCms\Cms\Element\Exceptions\InvalidElementException;
use CraftCms\Cms\Element\Exceptions\UnsupportedSiteException;
use craft\mail\Mailer;
use craft\mail\Message;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Models\Order as OrderRecord;
use CraftCms\Commerce\Payment\Events\UpdatePrimaryPaymentSourceEvent;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use yii\base\Event;
use yii\mail\MailEvent;

#[Singleton]
class Customers
{
    public const string EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE = 'updatePrimaryPaymentSource';

    public function savePrimaryShippingAddressId(User $user, ?int $addressId): bool
    {
        $customerRecord = $this->ensureCustomer($user);
        $customerRecord->primaryShippingAddressId = $addressId;
        /** @var User|\craft\commerce\behaviors\CustomerBehavior $user */
        $user->primaryShippingAddressId = $addressId;
        return $customerRecord->save();
    }

    public function savePrimaryBillingAddressId(User $user, ?int $addressId): bool
    {
        $customerRecord = $this->ensureCustomer($user);
        $customerRecord->primaryBillingAddressId = $addressId;
        /** @var User|\craft\commerce\behaviors\CustomerBehavior $user */
        $user->primaryBillingAddressId = $addressId;
        return $customerRecord->save();
    }

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

        /** @var User|\craft\commerce\behaviors\CustomerBehavior $user */
        $user->primaryPaymentSourceId = $paymentSourceId;

        if ($originalPaymentSourceId != $paymentSourceId) {
            $event = new UpdatePrimaryPaymentSourceEvent(
                customer: $user,
                previousPrimaryPaymentSourceId: $originalPaymentSourceId,
                newPrimaryPaymentSourceId: $paymentSourceId,
            );

            // TODO: migrate event firing to Laravel once event system is bridged
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getCustomers()->trigger(self::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE, $event);
        }

        return true;
    }

    /**
     * Handle user login.
     */
    public function loginHandler(): void
    {
        $impersonating = \Craft::$app->getSession()->get(User::IMPERSONATE_KEY) !== null;
        // Don't allow transition of current cart to a user that is being impersonated.
        if ($impersonating) {
            app(Carts::class)->forgetCart();
        }

        app(Carts::class)->restorePreviousCartForCurrentUser();
    }

    /**
     * Sets the last used addresses on the customer on order completion.
     *
     * Consolidates any other orders using the same email address.
     *
     * Duplicates the address records used for the order so they are independent to the
     * customers address book.
     */
    public function orderCompleteHandler(Order $order): void
    {
        // Create a user account if requested
        if ($order->registerUserOnOrderComplete) {
            $this->activateUserFromOrder($order);
        }

        // Did they want to save addresses to the customers address book?
        if ($order->saveBillingAddressOnOrderComplete || $order->saveShippingAddressOnOrderComplete) {
            $this->saveAddressesFromOrder($order);
        }

        // clear the primary address flags if they were set as it only applies to the cart
        if ($order->makePrimaryBillingAddress || $order->makePrimaryShippingAddress) {
            OrderRecord::query()->where('id', $order->id)->update([
                'makePrimaryBillingAddress' => false,
                'makePrimaryShippingAddress' => false,
            ]);
        }
    }

    /**
     * @param Order[] $orders
     * @return Order[]
     */
    public function eagerLoadCustomerForOrders(array $orders): array
    {
        $customerIds = collect($orders)->pluck('customerId')->filter()->all();
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
     * @throws ElementNotFoundException
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
            DB::table($table)->where($column, $fromId)->update([$column => $toId]);
        }

        $previousUses = DB::table(Table::CUSTOMER_DISCOUNTUSES)->where('customerId', $fromId)->pluck('uses', 'discountId');
        $toUses = DB::table(Table::CUSTOMER_DISCOUNTUSES)->where('customerId', $toId)->pluck('uses', 'discountId');

        foreach ($previousUses as $discountId => $uses) {
            if ($toUses->has($discountId)) {
                DB::table(Table::CUSTOMER_DISCOUNTUSES)
                    ->where('customerId', $toId)
                    ->where('discountId', $discountId)
                    ->increment('uses', $uses);
            } else {
                DB::table(Table::CUSTOMER_DISCOUNTUSES)->insert([
                    'uses' => $uses,
                    'customerId' => $toId,
                    'discountId' => $discountId,
                ]);
            }

            // Remove uses from fromCustomer
            DB::table(Table::CUSTOMER_DISCOUNTUSES)
                ->where('customerId', $fromId)
                ->where('discountId', $discountId)
                ->update(['uses' => 0]);
        }

        $fromEmail = $fromUser->email;
        $toEmail = $toUser->email;

        DB::table(Table::ORDERS)->where('email', $fromEmail)->update(['email' => $toEmail]);

        return true;
    }

    /**
     * @throws InvalidElementException
     * @throws UnsupportedSiteException
     */
    private function saveAddressesFromOrder(Order $order): void
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
            $newAddress = \Craft::$app->getElements()->duplicateElement(
                $order->getBillingAddress(),
                [
                    'primaryOwner' => $order->getCustomer(),
                    'owner' => $order->getCustomer(),
                ]
            );
            $newSourceBillingAddressId = $newAddress->id;
            $newSourceShippingAddressId = $newAddress->id;
        } else {
            if ($saveBillingAddress) {
                $newBillingAddress = \Craft::$app->getElements()->duplicateElement($order->getBillingAddress(),
                    [
                        'primaryOwner' => $order->getCustomer(),
                        'owner' => $order->getCustomer(),
                    ]
                );
                $newSourceBillingAddressId = $newBillingAddress->id;
            }

            if ($saveShippingAddress) {
                $newShippingAddress = \Craft::$app->getElements()->duplicateElement(
                    $order->getShippingAddress(),
                    [
                        'primaryOwner' => $order->getCustomer(),
                        'owner' => $order->getCustomer(),
                    ]
                );
                $newSourceShippingAddressId = $newShippingAddress->id;
            }
        }

        if ($newSourceBillingAddressId) {
            $order->sourceBillingAddressId = $newSourceBillingAddressId;
        }

        if ($newSourceShippingAddressId) {
            $order->sourceShippingAddressId = $newSourceShippingAddressId;
        }

        // Since we saved the primary addresses, we can now set the primary if they chose that also
        if ($order->makePrimaryShippingAddress && $order->sourceShippingAddressId) {
            $this->savePrimaryShippingAddressId($order->getCustomer(), $order->sourceShippingAddressId);
        }

        if ($order->makePrimaryBillingAddress && $order->sourceBillingAddressId) {
            $this->savePrimaryBillingAddressId($order->getCustomer(), $order->sourceBillingAddressId);
        }

        // Manually update the order DB record to avoid looped element saves
        if ($newSourceBillingAddressId || $newSourceShippingAddressId) {
            OrderRecord::query()->where('id', $order->id)->update([
                'sourceBillingAddressId' => $order->sourceBillingAddressId,
                'sourceShippingAddressId' => $order->sourceShippingAddressId,
            ]);
        }
    }

    /**
     * Makes sure the user has an email address and sets them to pending and sends the activation email.
     */
    private function activateUserFromOrder(Order $order): void
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

        if (property_exists($user, 'affiliatedSiteId')) {
            $user->affiliatedSiteId = $order->orderSiteId;
        }

        if (\Craft::$app->getElements()->saveElement($user)) {
            \Craft::$app->getUsers()->assignUserToDefaultGroup($user);

            Event::once(Mailer::class, Mailer::EVENT_BEFORE_PREP, function(MailEvent $event) use ($user) {
                if (!$event->message instanceof Message) {
                    return;
                }

                if ($event->message->key !== 'account_activation') {
                    return;
                }

                if ($event->message->siteId === null && property_exists($user, 'affiliatedSiteId') && $user->affiliatedSiteId) {
                    $event->message->siteId = $user->affiliatedSiteId;
                }
            });

            $emailSent = \Craft::$app->getUsers()->sendActivationEmail($user);

            if (!$emailSent) {
                \Craft::warning('"registerUserOnOrderComplete" used to create the user, but couldn\'t send an activation email. Check your email settings.', __METHOD__);
            }

            if ($billingAddress || $shippingAddress) {
                $newAttributes = [
                    'owner' => $user,
                    'primaryOwner' => $user,
                ];

                // If there is only one address make sure we don't add duplicates to the user
                if ($order->hasMatchingAddresses()) {
                    $newAttributes['title'] = \Craft::t('app', 'Address');
                    $shippingAddress = null;
                }

                // Copy addresses to user
                if ($billingAddress) {
                    $newBillingAddress = \Craft::$app->getElements()->duplicateElement($billingAddress, $newAttributes);

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
                    $newShippingAddress = \Craft::$app->getElements()->duplicateElement($shippingAddress, $newAttributes);

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
            \Craft::warning('Could not create user on order completion.', __METHOD__);
            \Craft::warning($errors, __METHOD__);
        }
    }
}
