<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\base\SavableComponentInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\elements\User;

/**
 * SubscriptionGatewayInterface defines the common interface to be implemented by gateway classes that support subscriptions.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface SubscriptionGatewayInterface extends SavableComponentInterface
{
    /**
     * Cancels a subscription.
     *
     * @param Subscription $subscription the subscription to cancel
     * @param CancelSubscriptionForm $parameters additional parameters to use
     * @return SubscriptionResponseInterface
     * @throws SubscriptionException for all subscription-related errors.
     */
    public function cancelSubscription(Subscription $subscription, CancelSubscriptionForm $parameters): SubscriptionResponseInterface;

    /**
     * Returns the next payment amount for a subscription, taking into account all discounts.
     *
     * @param Subscription $subscription
     * @return string next payment amount with currency code
     */
    public function getNextPaymentAmount(Subscription $subscription): string;

    /**
     * Returns a list of subscription payments for a given subscription.
     *
     * @param Subscription $subscription
     * @return SubscriptionPayment[]
     */
    public function getSubscriptionPayments(Subscription $subscription): array;

    /**
     * Refresh the subscription payment history for a given subscription.
     *
     * @param Subscription $subscription
     */
    public function refreshPaymentHistory(Subscription $subscription);

    /**
     * Returns a subscription plan by its reference
     *
     * @param string $reference
     * @return string
     */
    public function getSubscriptionPlanByReference(string $reference): string;

    /**
     * Returns all subscription plans as array containing hashes with `reference` and `name` as keys.
     *
     * @return array
     */
    public function getSubscriptionPlans(): array;

    /**
     * Reactivates a subscription.
     *
     * @param Subscription $subscription the canceled subscription to reactivate
     * @return SubscriptionResponseInterface
     * @throws NotImplementedException
     */
    public function reactivateSubscription(Subscription $subscription): SubscriptionResponseInterface;

    /**
     * Subscribe user to a plan.
     *
     * @param User $user the Craft user to subscribe
     * @param Plan $plan the plan to subscribe to
     * @param SubscriptionForm $parameters additional parameters to use
     * @return SubscriptionResponseInterface
     * @throws SubscriptionException for all subscription-related errors.
     */
    public function subscribe(User $user, Plan $plan, SubscriptionForm $parameters): SubscriptionResponseInterface;

    /**
     * Switch a subscription to a different subscription plan.
     *
     * @param Subscription $subscription the subscription to modify
     * @param Plan $plan the plan to change the subscription to
     * @param SwitchPlansForm $parameters additional parameters to use
     * @return SubscriptionResponseInterface
     */
    public function switchSubscriptionPlan(Subscription $subscription, Plan $plan, SwitchPlansForm $parameters): SubscriptionResponseInterface;

    /**
     * Returns whether this gateway supports reactivating subscriptions.
     *
     * @return bool
     */
    public function supportsReactivation(): bool;

    /**
     * Returns whether this gateway supports switching plans.
     *
     * @return bool
     */
    public function supportsPlanSwitch(): bool;

    /**
     * Returns whether this subscription has billing issues.
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function getHasBillingIssues(Subscription $subscription): bool;

    /**
     * Return a description of the billing issue (if any) with this subscription.
     *
     * @param Subscription $subscription
     * @return string
     */
    public function getBillingIssueDescription(Subscription $subscription): string;

    /**
     * Return the form HTML for resolving the billing issue (if any) with this subscription.
     *
     * @param Subscription $subscription
     * @return string
     */
    public function getBillingIssueResolveFormHtml(Subscription $subscription): string;
}
