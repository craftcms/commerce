<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGatewayInterface;
use craft\commerce\elements\deletionblockers\SubscriptionCustomersDeletionBlocker;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\commerce\Plugin;
use craft\commerce\records\Subscription as SubscriptionRecord;
use CraftCms\Cms\Element\ElementCollection;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\Element\Queries\Exceptions\ElementNotFoundException;
use craft\events\ConfigEvent;
use craft\events\DefineElementDeletionBlockersEvent;
use craft\helpers\DateTimeHelper;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Commerce\Subscription\Events\CancelSubscriptionEvent;
use CraftCms\Commerce\Subscription\Events\CreateSubscriptionEvent;
use CraftCms\Commerce\Subscription\Events\SubscriptionEvent;
use CraftCms\Commerce\Subscription\Events\SubscriptionPaymentEvent;
use CraftCms\Commerce\Subscription\Events\SubscriptionSwitchPlansEvent;
use DateTime;
use Illuminate\Container\Attributes\Singleton;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use function CraftCms\Cms\t;

#[Singleton]
class Subscriptions
{
    public const string EVENT_AFTER_EXPIRE_SUBSCRIPTION = 'afterExpireSubscription';

    public const string EVENT_BEFORE_CREATE_SUBSCRIPTION = 'beforeCreateSubscription';

    public const string EVENT_AFTER_CREATE_SUBSCRIPTION = 'afterCreateSubscription';

    public const string EVENT_BEFORE_REACTIVATE_SUBSCRIPTION = 'beforeReactivateSubscription';

    public const string EVENT_AFTER_REACTIVATE_SUBSCRIPTION = 'afterReactivateSubscription';

    public const string EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN = 'beforeSwitchSubscriptionPlan';

    public const string EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN = 'afterSwitchSubscriptionPlan';

    public const string EVENT_BEFORE_CANCEL_SUBSCRIPTION = 'beforeCancelSubscription';

    public const string EVENT_AFTER_CANCEL_SUBSCRIPTION = 'afterCancelSubscription';

    public const string EVENT_BEFORE_UPDATE_SUBSCRIPTION = 'beforeUpdateSubscription';

    public const string EVENT_RECEIVE_SUBSCRIPTION_PAYMENT = 'receiveSubscriptionPayment';

    public const string CONFIG_FIELDLAYOUT_KEY = 'commerce.subscriptions.fieldLayouts';

    /**
     * Handle field layout change.
     *
     * @throws Exception
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = \Craft::$app->getFields();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Subscription::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Subscription::class)->id;
        $layout->type = Subscription::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);
    }

    /**
     * Handle field layout being deleted.
     */
    public function handleDeletedFieldLayout(): void
    {
        \Craft::$app->getFields()->deleteLayoutsByType(Subscription::class);
    }

    /**
     * Prevent deleting a user if they have any subscriptions - active or otherwise.
     */
    public function beforeDeleteUserHandler(DefineElementDeletionBlockersEvent $event): void
    {
        /** @var ElementCollection<int|string, Subscription> $subscriptions */
        $subscriptions = Subscription::find()
            ->userId($event->elements->ids()->all())
            ->status(null)
            ->limit(null)
            ->collect();

        foreach ($subscriptions->groupBy(fn(Subscription $subscription) => (string)($subscription->gatewayId ?? 0)) as $gatewaySubscriptions) {
            /** @var Subscription $first */
            $first = $gatewaySubscriptions->first();
            $gateway = $first->getGateway();

            if (!$gateway instanceof SubscriptionGatewayInterface) {
                continue;
            }

            $event->blockers[] = new SubscriptionCustomersDeletionBlocker(
                $event->elements,
                $event->hardDelete,
                [
                    'gatewayId' => $first->gatewayId,
                    'subscriptions' => $gatewaySubscriptions,
                ]
            );
        }
    }

    /**
     * Expire a subscription.
     *
     * @param Subscription $subscription subscription to expire
     * @param DateTime|null $dateTime expiry date time
     * @return bool whether successfully expired subscription
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable if cannot expire subscription
     */
    public function expireSubscription(Subscription $subscription, ?DateTime $dateTime = null): bool
    {
        $subscription->isExpired = true;
        $subscription->dateExpired = $dateTime;

        if (!$subscription->dateExpired) {
            $subscription->dateExpired = DateTimeHelper::toDateTime('now');
        }

        \Craft::$app->getElements()->saveElement($subscription, false);

        // Raise 'afterExpireSubscription' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getSubscriptions()->hasEventHandlers(self::EVENT_AFTER_EXPIRE_SUBSCRIPTION)) {
            $event = new SubscriptionEvent(subscription: $subscription);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_AFTER_EXPIRE_SUBSCRIPTION, $event);
        }

        return true;
    }

    /**
     * Returns subscription count for a plan.
     */
    public function getSubscriptionCountByPlanId(int $planId): int
    {
        return SubscriptionRecord::find()->where(['planId' => $planId])->count();
    }

    /**
     * Return true if the user has any subscriptions at all, even expired ones.
     */
    public function doesUserHaveSubscriptions(int $userId): bool
    {
        return (bool)SubscriptionRecord::find()->where(['userId' => $userId])->count();
    }

    /**
     * Subscribe a user to a subscription plan.
     *
     * @param User $user the user subscribing to a plan
     * @param Plan $plan the plan the user is being subscribed to
     * @param SubscriptionForm $parameters array of additional parameters to use
     * @param array $fieldValues array of content field values to set
     * @return Subscription the subscription
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException if something went wrong during subscription
     * @throws Throwable
     */
    public function createSubscription(User $user, Plan $plan, SubscriptionForm $parameters, array $fieldValues = []): Subscription
    {
        $gateway = $plan->getGateway();

        // Raise 'beforeCreateSubscription' event
        $event = new CreateSubscriptionEvent(user: $user, plan: $plan, parameters: $parameters);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_BEFORE_CREATE_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            $error = t('Subscription for {user} to {plan} prevented by a plugin.', [
                'user' => $user->getFriendlyName(),
                'plan' => (string)$plan,
            ], category: 'commerce');

            \Craft::error($error, __METHOD__);

            throw new SubscriptionException(t('Unable to subscribe at this time.', category: 'commerce'));
        }

        $response = $gateway->subscribe($user, $plan, $event->parameters);

        $failedToStart = $response->isInactive();

        $subscription = new Subscription();
        $subscription->userId = $user->id;
        $subscription->planId = $plan->id;
        $subscription->gatewayId = $plan->gatewayId;
        $subscription->orderId = null;
        $subscription->reference = $response->getReference();
        $subscription->trialDays = $response->getTrialDays();
        $subscription->nextPaymentDate = $response->getNextPaymentDate();
        $subscription->subscriptionData = $response->getData();
        $subscription->isCanceled = false;
        $subscription->isExpired = false;
        $subscription->hasStarted = !$failedToStart;
        $subscription->isSuspended = $failedToStart;

        if ($failedToStart) {
            $subscription->dateSuspended = DateTimeHelper::toDateTime('now');
        }

        $subscription->setFieldValues($fieldValues);

        \Craft::$app->getElements()->saveElement($subscription, false);

        // Raise 'afterCreateSubscription' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getSubscriptions()->hasEventHandlers(self::EVENT_AFTER_CREATE_SUBSCRIPTION)) {
            $afterEvent = new SubscriptionEvent(subscription: $subscription);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_AFTER_CREATE_SUBSCRIPTION, $afterEvent);
        }

        return $subscription;
    }

    /**
     * Reactivate a subscription.
     *
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        $gateway = $subscription->getGateway();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('Gateway does not support subscriptions.');
        }

        // Raise 'beforeReactivateSubscription' event
        $event = new SubscriptionEvent(subscription: $subscription);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_BEFORE_REACTIVATE_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            $error = t('Could not reactivate "{reference}".', ['reference' => $subscription->reference], category: 'commerce');

            \Craft::error($error, __METHOD__);

            return false;
        }

        $response = $gateway->reactivateSubscription($subscription);

        if (!$response->isScheduledForCancellation()) {
            $subscription->isCanceled = false;
            $subscription->dateCanceled = null;
            $subscription->subscriptionData = $response->getData();

            \Craft::$app->getElements()->saveElement($subscription, false);

            // Raise 'afterReactivateSubscription' event
            // TODO: migrate event firing to Laravel once event system is bridged
            /** @phpstan-ignore-next-line */
            if (Plugin::getInstance()->getSubscriptions()->hasEventHandlers(self::EVENT_AFTER_REACTIVATE_SUBSCRIPTION)) {
                $afterEvent = new SubscriptionEvent(subscription: $subscription);
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_AFTER_REACTIVATE_SUBSCRIPTION, $afterEvent);
            }

            return true;
        }

        return false;
    }

    /**
     * Switch a subscription to a different subscription plan.
     *
     * @param Subscription $subscription the subscription to modify
     * @param Plan $plan the plan to change the subscription to
     * @param SwitchPlansForm $parameters additional parameters to use
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function switchSubscriptionPlan(Subscription $subscription, Plan $plan, SwitchPlansForm $parameters): bool
    {
        $gateway = $subscription->getGateway();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('Gateway does not support subscriptions.');
        }

        $oldPlan = $subscription->getPlan();

        if (!$plan->canSwitchFrom($oldPlan)) {
            throw new InvalidConfigException('The migration between these plans is not allowed.');
        }

        // Raise 'beforeSwitchSubscriptionPlan' event
        $event = new SubscriptionSwitchPlansEvent(oldPlan: $oldPlan, subscription: $subscription, newPlan: $plan, parameters: $parameters);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN, $event);

        if (!$event->isValid) {
            $error = t('Could not switch "{reference}" to "{plan}".', [
                'reference' => $subscription->reference,
                'plan' => $plan->reference,
            ], category: 'commerce');

            \Craft::error($error, __METHOD__);

            return false;
        }

        $response = $gateway->switchSubscriptionPlan($subscription, $plan, $parameters);

        $subscription->planId = $plan->id;
        $subscription->nextPaymentDate = $response->getNextPaymentDate();
        $subscription->subscriptionData = $response->getData();
        $subscription->isCanceled = false;
        $subscription->isExpired = false;

        \Craft::$app->getElements()->saveElement($subscription);

        // Raise 'afterSwitchSubscriptionPlan' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getSubscriptions()->hasEventHandlers(self::EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN)) {
            $afterEvent = new SubscriptionSwitchPlansEvent(oldPlan: $oldPlan, subscription: $subscription, newPlan: $plan, parameters: $parameters);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN, $afterEvent);
        }

        return true;
    }

    /**
     * Cancel a subscription.
     *
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException if something went wrong when canceling subscription
     */
    public function cancelSubscription(Subscription $subscription, CancelSubscriptionForm $parameters): bool
    {
        $gateway = $subscription->getGateway();

        if (!$gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidConfigException('Gateway does not support subscriptions.');
        }

        // Raise 'beforeCancelSubscription' event
        $event = new CancelSubscriptionEvent(subscription: $subscription, parameters: $parameters);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_BEFORE_CANCEL_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            $error = t('Could not cancel "{reference}".', ['reference' => $subscription->reference], category: 'commerce');

            \Craft::error($error, __METHOD__);

            return false;
        }

        $response = $gateway->cancelSubscription($subscription, $parameters);

        if ($response->isCanceled() || $response->isScheduledForCancellation()) {
            if ($response->isScheduledForCancellation()) {
                $subscription->isCanceled = true;
                $subscription->dateCanceled = DateTimeHelper::toDateTime('now');
            }

            if ($response->isCanceled()) {
                $subscription->isExpired = true;
                $subscription->isCanceled = true;
                $subscription->dateCanceled = DateTimeHelper::toDateTime('now');
                $subscription->dateExpired = DateTimeHelper::toDateTime('now');
            }

            $subscription->setSubscriptionData($response->getData());

            try {
                \Craft::$app->getElements()->saveElement($subscription, false);

                // Raise 'afterCancelSubscription' event
                // TODO: migrate event firing to Laravel once event system is bridged
                /** @phpstan-ignore-next-line */
                if (Plugin::getInstance()->getSubscriptions()->hasEventHandlers(self::EVENT_AFTER_CANCEL_SUBSCRIPTION)) {
                    $afterEvent = new CancelSubscriptionEvent(subscription: $subscription, parameters: $parameters);
                    /** @phpstan-ignore-next-line */
                    Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_AFTER_CANCEL_SUBSCRIPTION, $afterEvent);
                }
            } catch (Throwable $exception) {
                \Craft::warning('Failed to cancel subscription ' . $subscription->reference . ': ' . $exception->getMessage());

                throw new SubscriptionException(t('Unable to cancel subscription at this time.', category: 'commerce'));
            }
        }

        return true;
    }

    /**
     * Update a subscription.
     *
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function updateSubscription(Subscription $subscription): bool
    {
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getSubscriptions()->hasEventHandlers(self::EVENT_BEFORE_UPDATE_SUBSCRIPTION)) {
            $event = new SubscriptionEvent(subscription: $subscription);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_BEFORE_UPDATE_SUBSCRIPTION, $event);
        }

        return \Craft::$app->getElements()->saveElement($subscription);
    }

    /**
     * Receive a payment for a subscription.
     *
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function receivePayment(Subscription $subscription, SubscriptionPayment $payment, DateTime $paidUntil): bool
    {
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getSubscriptions()->hasEventHandlers(self::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT)) {
            $event = new SubscriptionPaymentEvent(subscription: $subscription, payment: $payment, paidUntil: $paidUntil);
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getSubscriptions()->trigger(self::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT, $event);
        }

        $subscription->nextPaymentDate = $paidUntil;

        return \Craft::$app->getElements()->saveElement($subscription);
    }
}
