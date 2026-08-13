<?php

namespace craft\commerce\services;

use craft\commerce\base\Plan;
use craft\commerce\elements\Subscription;
use CraftCms\Commerce\Subscription\Exceptions\SubscriptionException;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\events\ConfigEvent;
use craft\events\DefineElementDeletionBlockersEvent;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Subscription\Subscriptions::class)` instead.
 */
class Subscriptions extends Component
{
    public const EVENT_AFTER_EXPIRE_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_AFTER_EXPIRE_SUBSCRIPTION;

    public const EVENT_BEFORE_CREATE_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_BEFORE_CREATE_SUBSCRIPTION;

    public const EVENT_AFTER_CREATE_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_AFTER_CREATE_SUBSCRIPTION;

    public const EVENT_BEFORE_REACTIVATE_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_BEFORE_REACTIVATE_SUBSCRIPTION;

    public const EVENT_AFTER_REACTIVATE_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_AFTER_REACTIVATE_SUBSCRIPTION;

    public const EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN;

    public const EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN;

    public const EVENT_BEFORE_CANCEL_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_BEFORE_CANCEL_SUBSCRIPTION;

    public const EVENT_AFTER_CANCEL_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_AFTER_CANCEL_SUBSCRIPTION;

    public const EVENT_BEFORE_UPDATE_SUBSCRIPTION = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_BEFORE_UPDATE_SUBSCRIPTION;

    public const EVENT_RECEIVE_SUBSCRIPTION_PAYMENT = \CraftCms\Commerce\Subscription\Subscriptions::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT;

    public const CONFIG_FIELDLAYOUT_KEY = \CraftCms\Commerce\Subscription\Subscriptions::CONFIG_FIELDLAYOUT_KEY;

    /**
     * @throws Exception
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Subscription\Subscriptions::class)->handleChangedFieldLayout($event);
    }

    public function handleDeletedFieldLayout(): void
    {
        app(\CraftCms\Commerce\Subscription\Subscriptions::class)->handleDeletedFieldLayout();
    }

    public function beforeDeleteUserHandler(DefineElementDeletionBlockersEvent $event): void
    {
        app(\CraftCms\Commerce\Subscription\Subscriptions::class)->beforeDeleteUserHandler($event);
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function expireSubscription(Subscription $subscription, ?DateTime $dateTime = null): bool
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->expireSubscription($subscription, $dateTime);
    }

    public function getSubscriptionCountByPlanId(int $planId): int
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->getSubscriptionCountByPlanId($planId);
    }

    public function doesUserHaveSubscriptions(int $userId): bool
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->doesUserHaveSubscriptions($userId);
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException if something went wrong during subscription
     * @throws Throwable
     */
    public function createSubscription(User $user, Plan $plan, SubscriptionForm $parameters, array $fieldValues = []): Subscription
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->createSubscription($user, $plan, $parameters, $fieldValues);
    }

    /**
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->reactivateSubscription($subscription);
    }

    /**
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function switchSubscriptionPlan(Subscription $subscription, Plan $plan, SwitchPlansForm $parameters): bool
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->switchSubscriptionPlan($subscription, $plan, $parameters);
    }

    /**
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException if something went wrong when canceling subscription
     */
    public function cancelSubscription(Subscription $subscription, CancelSubscriptionForm $parameters): bool
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->cancelSubscription($subscription, $parameters);
    }

    /**
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function updateSubscription(Subscription $subscription): bool
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->updateSubscription($subscription);
    }

    /**
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function receivePayment(Subscription $subscription, SubscriptionPayment $payment, DateTime $paidUntil): bool
    {
        return app(\CraftCms\Commerce\Subscription\Subscriptions::class)->receivePayment($subscription, $payment, $paidUntil);
    }
}
