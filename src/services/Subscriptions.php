<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGatewayInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\records\Subscription as SubscriptionRecord;
use craft\elements\User;
use craft\helpers\Db;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Susbcriptions service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Subscriptions extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns susbcription count for a plan.
     *
     * @param int $planId
     *
     * @return int
     */
    public function getSubscriptionCountForPlanById(int $planId): int
    {
        return SubscriptionRecord::find()->where(['planId' => $planId])->count();
    }

    /**
     * Subscribe a user to a subscription plan.
     *
     * @param User             $user       the user subscribing to a plan
     * @param Plan             $plan       the plan the user is being subscribed to
     * @param SubscriptionForm $parameters array of additional parameters to use
     * @param Order            $order      order, if subscribing is part of an order
     *
     * @return bool the result
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException if something went wrong during subscription
     */
    public function subscribe(User $user, Plan $plan, SubscriptionForm $parameters, Order $order = null): bool
    {
        $gateway = $plan->getGateway();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('Gateway does not support subscriptions.');
        }

        $response = $gateway->subscribe($user, $plan, $parameters);

        $subscription = new Subscription();
        $subscription->userId = $user->id;
        $subscription->planId = $plan->id;
        $subscription->gatewayId = $plan->gatewayId;
        $subscription->orderId = $order ? $order->id : null;
        $subscription->reference = $response->getReference();
        $subscription->trialDays = $response->getTrialDays();
        $subscription->nextPaymentDate = $response->getNextPaymentDate();
        $subscription->subscriptionData = $response->getData();
        $subscription->isCanceled = false;
        $subscription->isExpired = false;

        try{
            return Craft::$app->getElements()->saveElement($subscription);
        } catch (\Throwable $exception) {
            Craft::warning('Failed to subscribe '.$user.' to '.$plan.': '.$exception->getMessage());

            throw new SubscriptionException(Craft::t('commerce', 'Unable to subscribe at this time.'));}

    }

    /**
     * Reactivate a subscription.
     *
     * @param Subscription           $subscription
     *
     * @return bool
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException  if something went wrong when reactivating subscription
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        $gateway = $subscription->getGateway();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('Gateway does not support subscriptions.');
        }

        $response = $gateway->reactivateSubscription($subscription);

        if (!$response->isScheduledForCancelation()) {
            $subscription->isCanceled = false;
            $subscription->dateCanceled = null;

            try{
                return Craft::$app->getElements()->saveElement($subscription);
            } catch (\Throwable $exception) {
                Craft::warning('Failed to cancel subscription '.$subscription->reference.': '.$exception->getMessage());

                throw new SubscriptionException(Craft::t('commerce', 'Unable to reactivate subscription at this time.'));
            }
        }

        return false;
    }

    /**
     * Cancel a subscription.
     *
     * @param Subscription           $subscription
     * @param CancelSubscriptionForm $parameters
     *
     * @return bool
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException  if something went wrong when canceling subscription
     */
    public function cancelSubscription(Subscription $subscription, CancelSubscriptionForm $parameters): bool
    {
        $gateway = $subscription->getGateway();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('Gateway does not support subscriptions.');
        }

        $response = $gateway->cancelSubscription($subscription, $parameters);

        if ($response->isCanceled() || $response->isScheduledForCancelation()) {
            if ($response->isScheduledForCancelation()) {
                $subscription->isCanceled = true;
                $subscription->dateCanceled = Db::prepareDateForDb(new \DateTime());
            }

            if ($response->isCanceled()) {
                $subscription->isExpired = true;
                $subscription->dateExpired = Db::prepareDateForDb(new \DateTime());
            }

            try{
                return Craft::$app->getElements()->saveElement($subscription);
            } catch (\Throwable $exception) {
                Craft::warning('Failed to cancel subscription '.$subscription->reference.': '.$exception->getMessage());

                throw new SubscriptionException(Craft::t('commerce', 'Unable to cancel subscription at this time.'));
            }
        }

        return false;
    }
}
