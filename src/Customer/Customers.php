<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Customer;

use craft\commerce\Plugin;
use craft\mail\Mailer;
use craft\mail\Message;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Auth\Impersonation;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Element\Exceptions\InvalidElementException;
use CraftCms\Cms\Element\Exceptions\UnsupportedSiteException;
use CraftCms\Cms\Element\Queries\Exceptions\ElementNotFoundException;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Models\Customer as CustomerRecord;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Models\Order as OrderRecord;
use CraftCms\Commerce\Payment\Events\UpdatePrimaryPaymentSourceEvent;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use yii\base\Event;
use yii\mail\MailEvent;

use function CraftCms\Cms\t;

#[Singleton]
class Customers
{
    public const string EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE = 'updatePrimaryPaymentSource';

    public function savePrimaryShippingAddressId(User $user, ?int $addressId): bool
    {
        $customerRecord = $this->ensureCustomer($user);
        $customerRecord->primaryShippingAddressId = $addressId;
        /** @phpstan-ignore-next-line method.notFound (setPrimaryShippingAddressId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
        $user->setPrimaryShippingAddressId($addressId);
        return $customerRecord->save();
    }

    public function savePrimaryBillingAddressId(User $user, ?int $addressId): bool
    {
        $customerRecord = $this->ensureCustomer($user);
        $customerRecord->primaryBillingAddressId = $addressId;
        /** @phpstan-ignore-next-line method.notFound (setPrimaryBillingAddressId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
        $user->setPrimaryBillingAddressId($addressId);
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

        /** @phpstan-ignore-next-line method.notFound (setPrimaryPaymentSourceId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
        $user->setPrimaryPaymentSourceId($paymentSourceId);

        if ($originalPaymentSourceId != $paymentSourceId) {
            $event = new UpdatePrimaryPaymentSourceEvent(
                customer: $user,
                previousPrimaryPaymentSourceId: $originalPaymentSourceId,
                newPrimaryPaymentSourceId: $paymentSourceId,
            );

            // TODO: migrate event firing to Laravel once event system is bridged
            $legacyService = Plugin::getInstance()->getCustomers();
            if ($legacyService->hasEventHandlers(self::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE)) {
                /** @phpstan-ignore-next-line argument.type (TODO: migrate event firing to Laravel once event system is bridged) */
                $legacyService->trigger(self::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE, $event);
            }
        }

        return true;
    }

    /**
     * Handle user login.
     */
    public function loginHandler(): void
    {
        $impersonating = app(Impersonation::class)->isImpersonating();
        // Don't allow transition of current cart to a user that is being impersonated.
        if ($impersonating) {
            app(Carts::class)->forgetCart();
        }

        app(Carts::class)->restorePreviousCartForCurrentUser();
    }

    /**
     * Persists any primary billing/shipping address or payment source that was set on the user
     * this request, and keeps orders' `email` column in sync with the user's email address.
     *
     * Replaces `craft\commerce\behaviors\CustomerBehavior::afterSaveUserHandler()`.
     */
    public function afterSaveUserHandler(ElementSaved $event): void
    {
        /** @var User $user */
        $user = $event->element;

        /** @phpstan-ignore-next-line method.notFound (getPrimaryBillingAddressId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
        if ($user->getPrimaryBillingAddressId()) {
            /** @phpstan-ignore-next-line method.notFound (getPrimaryBillingAddressId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
            $this->savePrimaryBillingAddressId($user, $user->getPrimaryBillingAddressId());
        }

        /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddressId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
        if ($user->getPrimaryShippingAddressId()) {
            /** @phpstan-ignore-next-line method.notFound (getPrimaryShippingAddressId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
            $this->savePrimaryShippingAddressId($user, $user->getPrimaryShippingAddressId());
        }

        /** @phpstan-ignore-next-line method.notFound (getPrimaryPaymentSourceId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
        if ($user->getPrimaryPaymentSourceId()) {
            /** @phpstan-ignore-next-line method.notFound (getPrimaryPaymentSourceId() is added to User via a Macroable macro registered in Plugin::registerCustomerMacros(), not visible to static analysis) */
            $this->savePrimaryPaymentSourceId($user, $user->getPrimaryPaymentSourceId());
        }

        if ($user->email && $user->id) {
            DB::table(Table::ORDERS)
                ->where('customerId', $user->id)
                ->update(['email' => $user->email]);
        }
    }

    /**
     * Syncs an address's primary billing/shipping flags onto its owning user's customer record,
     * if the flags were set on the address this request.
     *
     * Replaces `craft\commerce\behaviors\CustomerAddressBehavior::afterPropagate()`.
     */
    public function afterSaveAddressHandler(ElementSaved $event): void
    {
        /** @var Address $address */
        $address = $event->element;

        if ($address->getIsDraft()) {
            return;
        }

        $owner = $address->getPrimaryOwner();

        if (!$owner instanceof User) {
            return;
        }

        if ($address->getIsDerivative()) {
            return;
        }

        $customer = $this->ensureCustomer($owner);

        /** @phpstan-ignore-next-line method.notFound (hasIsPrimaryBillingBeenSet()/getIsPrimaryBilling() are added to Address via Macroable macros registered in Plugin::registerCustomerAddressMacros(), not visible to static analysis) */
        if ($address->hasIsPrimaryBillingBeenSet() && ($address->getIsPrimaryBilling() || $customer->primaryBillingAddressId === $address->id)) {
            /** @phpstan-ignore-next-line method.notFound (getIsPrimaryBilling() is added to Address via a Macroable macro registered in Plugin::registerCustomerAddressMacros(), not visible to static analysis) */
            $this->savePrimaryBillingAddressId($owner, $address->getIsPrimaryBilling() ? $address->id : null);
        }

        /** @phpstan-ignore-next-line method.notFound (hasIsPrimaryShippingBeenSet()/getIsPrimaryShipping() are added to Address via Macroable macros registered in Plugin::registerCustomerAddressMacros(), not visible to static analysis) */
        if ($address->hasIsPrimaryShippingBeenSet() && ($address->getIsPrimaryShipping() || $customer->primaryShippingAddressId === $address->id)) {
            /** @phpstan-ignore-next-line method.notFound (getIsPrimaryShipping() is added to Address via a Macroable macro registered in Plugin::registerCustomerAddressMacros(), not visible to static analysis) */
            $this->savePrimaryShippingAddressId($owner, $address->getIsPrimaryShipping() ? $address->id : null);
        }
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
        $customerRecord = CustomerRecord::where('customerId', $user->id)->first();
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
                $now = now()->toDateTimeString();
                DB::table(Table::CUSTOMER_DISCOUNTUSES)->insert([
                    'uses' => $uses,
                    'customerId' => $toId,
                    'discountId' => $discountId,
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
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
            $newAddress = Elements::duplicateElement(
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
                $newBillingAddress = Elements::duplicateElement($order->getBillingAddress(),
                    [
                        'primaryOwner' => $order->getCustomer(),
                        'owner' => $order->getCustomer(),
                    ]
                );
                $newSourceBillingAddressId = $newBillingAddress->id;
            }

            if ($saveShippingAddress) {
                $newShippingAddress = Elements::duplicateElement(
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
            /** @phpstan-ignore-next-line nullsafe.neverNull (getBillingAddress()/getShippingAddress() genuinely return ?AddressElement) */
            $user->fullName = $billingAddress?->fullName ?? $shippingAddress?->fullName ?? '';
        }

        $user->username = $order->getEmail();
        $user->pending = true;
        $user->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);

        $user->affiliatedSiteId = $order->orderSiteId;

        if (Elements::saveElement($user)) {
            Users::assignUserToDefaultGroup($user);

            $setActivationEmailSiteId = function(MailEvent $event) use ($user, &$setActivationEmailSiteId) {
                Event::off(Mailer::class, Mailer::EVENT_BEFORE_PREP, $setActivationEmailSiteId);

                if (!$event->message instanceof Message) {
                    return;
                }

                if ($event->message->key !== 'account_activation') {
                    return;
                }

                if ($event->message->siteId === null && $user->affiliatedSiteId) {
                    $event->message->siteId = $user->affiliatedSiteId;
                }
            };
            Event::on(Mailer::class, Mailer::EVENT_BEFORE_PREP, $setActivationEmailSiteId);

            $emailSent = Users::sendActivationEmail($user);

            if (!$emailSent) {
                Log::warning('"registerUserOnOrderComplete" used to create the user, but couldn\'t send an activation email. Check your email settings.');
            }

            if ($billingAddress || $shippingAddress) {
                $newAttributes = [
                    'owner' => $user,
                    'primaryOwner' => $user,
                ];

                // If there is only one address make sure we don't add duplicates to the user
                if ($order->hasMatchingAddresses()) {
                    $newAttributes['title'] = t('Address', category: 'app');
                    $shippingAddress = null;
                }

                // Copy addresses to user
                if ($billingAddress) {
                    $newBillingAddress = Elements::duplicateElement($billingAddress, $newAttributes);

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
                    $newShippingAddress = Elements::duplicateElement($shippingAddress, $newAttributes);

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
            Log::warning('Could not create user on order completion.', ['errors' => $errors]);
        }
    }
}
