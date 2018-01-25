<?php

namespace craft\commerce\base;

use craft\base\SavableComponentInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\elements\User;

/**
 * SubscriptionGatewayInterface defines the common interface to be implemented by gateway classes that support subscriptions.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
interface SubscriptionGatewayInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Fetch a subscription plan by its reference
     *
     * @param string $reference
     *
     * @return string
     */
    public function getSubscriptionPlanByReference(string $reference): string;

    /**
     * Get all subscription plans as array containing hashes with `reference` and `name` as keys.
     *
     * @return array
     */
    public function getSubscriptionPlans(): array;

    /**
     * Subscribe user to a plan.
     *
     * @param User  $user       the Craft user to subscribe
     * @param Plan  $plan       the plan to subscribe to
     * @param array $paramaters additional parameters to use
     *
     * @return SubscriptionResponseInterface
     * @throws SubscriptionException for all subscription-related errors.
     */
    // TODO parameters -> subscriptionParameters
    public function subscribe(User $user, Plan $plan, array $parameters = []): SubscriptionResponseInterface;

    /**
     * Cancel a subscription.
     *
     * @param string $reference  the subscription reference
     * @param array  $parameters additional paramters touse
     *
     * @return SubscriptionResponseInterface
     * @throws SubscriptionException for all subscription-related errors.
     */
    // TODO parameters -> subscriptionParameters
    public function cancelSubscription(string $reference, array $parameters = []): SubscriptionResponseInterface;

    /**
     * Get a list of subscription payments for a given subscription.
     *
     * @param Subscription $subscription
     *
     * @return SubscriptionPayment[]
     */
    public function getSubscriptionPayments(Subscription $subscription): array;

}
