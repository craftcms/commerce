<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGatewayInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\records\Subscription as SubscriptionRecord;
use craft\elements\User;
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
     * @param User  $user       the user subscribing to a plan
     * @param Plan  $plan       the plan the user is being subscribed to
     * @param array $paramaters array of additional parameters to use
     * @param Order $order      order, if subscribing is part of an order
     *
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException if something went wrong during subscription
     */
    public function subscribe(User $user, Plan $plan, $paramaters = [], Order $order = null)
    {
        $gateway = $plan->getGateway();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('Gateway does not support subscriptions.');
        }

        $response = $gateway->subscribe($user, $plan, $paramaters);

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

        Craft::$app->getElements()->saveElement($subscription);
    }

}
