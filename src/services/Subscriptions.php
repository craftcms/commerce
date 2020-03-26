<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Field;
use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGatewayInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\errors\SubscriptionException;
use craft\commerce\events\CancelSubscriptionEvent;
use craft\commerce\events\CreateSubscriptionEvent;
use craft\commerce\events\SubscriptionEvent;
use craft\commerce\events\SubscriptionPaymentEvent;
use craft\commerce\events\SubscriptionSwitchPlansEvent;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\commerce\Plugin;
use craft\commerce\records\Subscription as SubscriptionRecord;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\events\ConfigEvent;
use craft\events\FieldEvent;
use craft\events\ModelEvent;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Subscriptions service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Subscriptions extends Component
{
    /**
     * @event SubscriptionEvent The event that is triggered after a subscription has expired.
     *
     * ```php
     * use craft\commerce\events\SubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_AFTER_EXPIRE_SUBSCRIPTION,
     *     function(SubscriptionEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     *         
     *         // Make a call to third party service to de-authorize a user
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_EXPIRE_SUBSCRIPTION = 'afterExpireSubscription';

    /**
     * @event CreateSubscriptionEvent The event that is triggered before a subscription is created.
     *
     * You may set the `isValid` property to `false` on the event to prevent the user from being subscribed to the plan.
     *
     * ```php
     * use craft\commerce\events\CreateSubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\elements\User;
     * use craft\commerce\base\Plan;
     * use craft\commerce\models\subscriptions\SubscriptionForm;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_BEFORE_CREATE_SUBSCRIPTION,
     *     function(CreateSubscriptionEvent $event) {
     *         // @var User $user
     *         $user = $event->user;
     *         // @var Plan $plan
     *         $plan = $event->plan;
     *         // @var SubscriptionForm $params
     *         $params = $event->parameters;
     * 
     *         // Set the trial days based on some business logic
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_CREATE_SUBSCRIPTION = 'beforeCreateSubscription';

    /**
     * @event SubscriptionEvent The event that is triggered after a subscription is created.
     *
     * ```php
     * use craft\commerce\events\SubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_AFTER_CREATE_SUBSCRIPTION,
     *     function(SubscriptionEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     * 
     *         // Call a third party service to authorize a user
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_CREATE_SUBSCRIPTION = 'afterCreateSubscription';

    /**
     * @event SubscriptionEvent TThe event that is triggered before a subscription gets reactivated.
     *
     * You may set the `isValid` property to `false` on the event to prevent the subscription from being reactivated.
     *
     * ```php
     * use craft\commerce\events\SubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_BEFORE_REACTIVATE_SUBSCRIPTION,
     *     function(SubscriptionEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     * 
     *         // Use business logic to determine whether the user can reactivate
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_REACTIVATE_SUBSCRIPTION = 'beforeReactivateSubscription';

    /**
     * @event SubscriptionEvent The event that is triggered after a subscription gets reactivated.
     *
     * ```php
     * use craft\commerce\events\SubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_AFTER_REACTIVATE_SUBSCRIPTION,
     *     function(SubscriptionEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     * 
     *         // Re-authorize the user with a third-party service
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_REACTIVATE_SUBSCRIPTION = 'afterReactivateSubscription';

    /**
     * @event SubscriptionSwitchPlansEvent The event that is triggered before a subscription is switched to a different plan.
     *
     * You may set the `isValid` property to `false` on the event to prevent the switch from happening.
     *
     * ```php
     * use craft\commerce\events\SubscriptionSwitchPlansEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\base\Plan;
     * use craft\commerce\elements\Subscription;
     * use craft\commerce\models\subscriptions\SwitchPlansForm;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN,
     *     function(SubscriptionSwitchPlansEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     *         // @var Plan $oldPlan
     *         $oldPlan = $event->oldPlan;
     *         // @var Plan $newPlan
     *         $newPlan = $event->newPlan;
     *         // @var SwitchPlansForm $params
     *         $params = $event->parameters;
     * 
     *         // Modify the switch parameters based on some business logic
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN = 'beforeSwitchSubscriptionPlan';

    /**
     * @event SubscriptionSwitchPlansEvent The event that is triggered after a subscription gets switched to a different plan.
     *
     * ```php
     * use craft\commerce\events\SubscriptionSwitchPlansEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\base\Plan;
     * use craft\commerce\elements\Subscription;
     * use craft\commerce\models\subscriptions\SwitchPlansForm;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN,
     *     function(SubscriptionSwitchPlansEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     *         // @var Plan $oldPlan
     *         $oldPlan = $event->oldPlan;
     *         // @var Plan $newPlan
     *         $newPlan = $event->newPlan;
     *         // @var SwitchPlansForm $params
     *         $params = $event->parameters;
     * 
     *         // Adjust the user’s permissions on a third party service
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN = 'afterSwitchSubscriptionPlan';

    /**
     * @event CancelSubscriptionEvent The event that is triggered before a subscription is canceled.
     *
     * You may set the `isValid` property to `false` on the event to prevent the subscription from being canceled.
     *
     * ```php
     * use craft\commerce\events\CancelSubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use craft\commerce\models\subscriptions\CancelSubscriptionForm;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_BEFORE_CANCEL_SUBSCRIPTION,
     *     function(CancelSubscriptionEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     *         // @var CancelSubscriptionForm $params
     *         $params = $event->parameters;
     * 
     *         // Check whether the user is permitted to cancel the subscription
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_CANCEL_SUBSCRIPTION = 'beforeCancelSubscription';

    /**
     * @event CancelSubscriptionEvent The event that is triggered after a subscription gets canceled.
     *
     * ```php
     * use craft\commerce\events\CancelSubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use craft\commerce\models\subscriptions\CancelSubscriptionForm;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_AFTER_CANCEL_SUBSCRIPTION,
     *     function(CancelSubscriptionEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     *         // @var CancelSubscriptionForm $params
     *         $params = $event->parameters;
     *
     *         // Refund the user for the remainder of the subscription
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_CANCEL_SUBSCRIPTION = 'afterCancelSubscription';

    /**
     * @event SubscriptionEvent The event that is triggered before a subscription gets updated. Typically this event is fired when subscription data is updated on the gateway.
     *
     * ```php
     * use craft\commerce\events\SubscriptionEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use yii\base\Event;
     * 
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_BEFORE_UPDATE_SUBSCRIPTION,
     *     function(SubscriptionEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     * 
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_UPDATE_SUBSCRIPTION = 'beforeUpdateSubscription';

    /**
     * @event SubscriptionPaymentEvent The event that is triggered when a subscription payment is received.
     *
     * ```php
     * use craft\commerce\events\SubscriptionPaymentEvent;
     * use craft\commerce\services\Subscriptions;
     * use craft\commerce\elements\Subscription;
     * use craft\commerce\models\subscriptions\SubscriptionPayment;
     * use DateTime;
     * use yii\base\Event;
     *
     * Event::on(
     *     Subscriptions::class,
     *     Subscriptions::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT,
     *     function(SubscriptionPaymentEvent $event) {
     *         // @var Subscription $subscription
     *         $subscription = $event->subscription;
     *         // @var SubscriptionPayment $payment
     *         $payment = $event->payment;
     *         // @var DateTime $until
     *         $until = $event->paidUntil;
     *
     *         // Update loyalty reward data
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_RECEIVE_SUBSCRIPTION_PAYMENT = 'receiveSubscriptionPayment';

    const CONFIG_FIELDLAYOUT_KEY = 'commerce.subscriptions.fieldLayouts';


    /**
     * Handle field layout change
     *
     * @param ConfigEvent $event
     */
    public function handleChangedFieldLayout(ConfigEvent $event)
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty($config = reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Subscription::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Subscription::class)->id;
        $layout->type = Subscription::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout);
    }

    /**
     * Prune a deleted field from subscription field layouts.
     *
     * @param FieldEvent $event
     */
    public function pruneDeletedField(FieldEvent $event)
    {
        /** @var Field $field */
        $field = $event->field;
        $fieldUid = $field->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $layoutData = $projectConfig->get(self::CONFIG_FIELDLAYOUT_KEY);

        // Prune the UID from field layouts.
        if (is_array($layoutData)) {
            foreach ($layoutData as $layoutUid => $layout) {
                if (!empty($layout['tabs'])) {
                    foreach ($layout['tabs'] as $tabUid => $tab) {
                        $projectConfig->remove(self::CONFIG_FIELDLAYOUT_KEY . '.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                    }
                }
            }
        }
    }

    /**
     * Handle field layout being deleted
     *
     * @param ConfigEvent $event
     */
    public function handleDeletedFieldLayout(ConfigEvent $event)
    {
        Craft::$app->getFields()->deleteLayoutsByType(Subscription::class);
    }

    /**
     * Prevent deleting a user if they have any subscriptions - active or otherwise.
     *
     * @param ModelEvent $event the event.
     */
    public function beforeDeleteUserHandler(ModelEvent $event)
    {
        /** @var User $user */
        $user = $event->sender;

        // If there are any subscriptions, make sure that this is not allowed.
        if ($this->doesUserHaveAnySubscriptions($user->id)) {
            $event->isValid = false;
        }
    }

    /**
     * Expire a subscription.
     *
     * @param Subscription $subscription subscription to expire
     * @param DateTime $dateTime expiry date time
     * @return bool whether successfully expired subscription
     * @throws Throwable if cannot expire subscription
     */
    public function expireSubscription(Subscription $subscription, DateTime $dateTime = null): bool
    {
        $subscription->isExpired = true;
        $subscription->dateExpired = $dateTime;

        if (!$subscription->dateExpired) {
            $subscription->dateExpired = Db::prepareDateForDb(new DateTime());
        }

        Craft::$app->getElements()->saveElement($subscription, false);

        // fire an 'expireSubscription' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_EXPIRE_SUBSCRIPTION)) {
            $this->trigger(self::EVENT_AFTER_EXPIRE_SUBSCRIPTION, new SubscriptionEvent([
                'subscription' => $subscription
            ]));
        }

        return true;
    }

    /**
     * Returns subscription count for a plan.
     *
     * @param int $planId
     * @return int
     */
    public function getSubscriptionCountForPlanById(int $planId): int
    {
        return SubscriptionRecord::find()->where(['planId' => $planId])->count();
    }

    /**
     * Return true if the user has any subscriptions at all, even expired ones.
     *
     * @param int $userId
     * @return bool
     */
    public function doesUserHaveAnySubscriptions(int $userId): bool
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
     * @throws InvalidConfigException if the gateway does not support subscriptions
     * @throws SubscriptionException if something went wrong during subscription
     */
    public function createSubscription(User $user, Plan $plan, SubscriptionForm $parameters, array $fieldValues = []): Subscription
    {
        $gateway = $plan->getGateway();

        // fire a 'beforeCreateSubscription' event
        $event = new CreateSubscriptionEvent(compact('user', 'plan', 'parameters'));
        $this->trigger(self::EVENT_BEFORE_CREATE_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            $error = Plugin::t( 'Subscription for {user} to {plan} prevented by a plugin.', [
                'user' => $user->getFriendlyName(),
                'plan' => (string)$plan
            ]);

            Craft::error($error, __METHOD__);

            throw new SubscriptionException(Plugin::t( 'Unable to subscribe at this time.'));
        }

        $response = $gateway->subscribe($user, $plan, $parameters);
        
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
            $subscription->dateSuspended = Db::prepareDateForDb(new DateTime());
        }

        $subscription->setFieldValues($fieldValues);

        Craft::$app->getElements()->saveElement($subscription, false);

        // Fire an 'afterCreateSubscription' event.
        if ($this->hasEventHandlers(self::EVENT_AFTER_CREATE_SUBSCRIPTION)) {
            $this->trigger(self::EVENT_AFTER_CREATE_SUBSCRIPTION, new SubscriptionEvent([
                'subscription' => $subscription
            ]));
        }

        return $subscription;
    }

    /**
     * Reactivate a subscription.
     *
     * @param Subscription $subscription
     * @return bool
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

        // fire a 'beforeReactivateSubscription' event
        $event = new SubscriptionEvent([
            'subscription' => $subscription,
        ]);
        $this->trigger(self::EVENT_BEFORE_REACTIVATE_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            $error = Plugin::t( 'Subscription "{reference}" reactivation was cancelled by a plugin.', [
                'reference' => $subscription->reference,
            ]);

            Craft::error($error, __METHOD__);

            return false;
        }

        $response = $gateway->reactivateSubscription($subscription);

        if (!$response->isScheduledForCancellation()) {
            $subscription->isCanceled = false;
            $subscription->dateCanceled = null;
            $subscription->subscriptionData = $response->getData();

            Craft::$app->getElements()->saveElement($subscription, false);

            // Fire a 'afterReactivateSubscription' event.
            if ($this->hasEventHandlers(self::EVENT_AFTER_REACTIVATE_SUBSCRIPTION)) {
                $this->trigger(self::EVENT_AFTER_REACTIVATE_SUBSCRIPTION, new SubscriptionEvent([
                    'subscription' => $subscription
                ]));
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
     * @return bool
     * @throws InvalidConfigException
     * @throws SubscriptionException
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

        // fire a 'beforeSwitchSubscriptionPlan' event
        $event = new SubscriptionSwitchPlansEvent([
            'oldPlan' => $oldPlan,
            'subscription' => $subscription,
            'newPlan' => $plan,
            'parameters' => $parameters
        ]);
        $this->trigger(self::EVENT_BEFORE_SWITCH_SUBSCRIPTION_PLAN, $event);

        if (!$event->isValid) {
            $error = Plugin::t( 'Subscription "{reference}" switch to "{plan}" was cancelled by a plugin.', [
                'reference' => $subscription->reference,
                'plan' => $plan->reference
            ]);

            Craft::error($error, __METHOD__);

            return false;
        }

        $response = $gateway->switchSubscriptionPlan($subscription, $plan, $parameters);

        $subscription->planId = $plan->id;
        $subscription->nextPaymentDate = $response->getNextPaymentDate();
        $subscription->subscriptionData = $response->getData();
        $subscription->isCanceled = false;
        $subscription->isExpired = false;

        Craft::$app->getElements()->saveElement($subscription);

        // fire an 'afterSwitchSubscriptionPlan' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN)) {
            $this->trigger(self::EVENT_AFTER_SWITCH_SUBSCRIPTION_PLAN, new SubscriptionSwitchPlansEvent([
                'oldPlan' => $oldPlan,
                'subscription' => $subscription,
                'newPlan' => $plan,
                'parameters' => $parameters
            ]));
        }

        return true;
    }

    /**
     * Cancel a subscription.
     *
     * @param Subscription $subscription
     * @param CancelSubscriptionForm $parameters
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

        // fire a 'beforeCancelSubscription' event
        $event = new CancelSubscriptionEvent(compact('subscription', 'parameters'));
        $this->trigger(self::EVENT_BEFORE_CANCEL_SUBSCRIPTION, $event);

        if (!$event->isValid) {
            $error = Plugin::t( 'Subscription "{reference}" cancellation was prevented by a plugin.', [
                'reference' => $subscription->reference,
            ]);

            Craft::error($error, __METHOD__);

            return false;
        }

        $response = $gateway->cancelSubscription($subscription, $parameters);

        if ($response->isCanceled() || $response->isScheduledForCancellation()) {
            if ($response->isScheduledForCancellation()) {
                $subscription->isCanceled = true;
                $subscription->dateCanceled = Db::prepareDateForDb(new DateTime());
            }

            if ($response->isCanceled()) {
                $subscription->isExpired = true;
                $subscription->isCanceled = true;
                $subscription->dateCanceled = Db::prepareDateForDb(new DateTime());
                $subscription->dateExpired = Db::prepareDateForDb(new DateTime());
            }

            $subscription->subscriptionData = $response->getData();

            try {
                Craft::$app->getElements()->saveElement($subscription, false);

                // fire an 'afterCancelSubscription' event
                if ($this->hasEventHandlers(self::EVENT_AFTER_CANCEL_SUBSCRIPTION)) {
                    $this->trigger(self::EVENT_AFTER_CANCEL_SUBSCRIPTION, new CancelSubscriptionEvent(compact('subscription', 'parameters')));
                }
            } catch (Throwable $exception) {
                Craft::warning('Failed to cancel subscription ' . $subscription->reference . ': ' . $exception->getMessage());

                throw new SubscriptionException(Plugin::t( 'Unable to cancel subscription at this time.'));
            }
        }

        return true;
    }

    /**
     * Update a subscription.
     *
     * @param Subscription $subscription
     * @return bool
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function updateSubscription(Subscription $subscription): bool
    {
        if ($this->hasEventHandlers(self::EVENT_BEFORE_UPDATE_SUBSCRIPTION)) {
            $this->trigger(self::EVENT_BEFORE_UPDATE_SUBSCRIPTION, new SubscriptionEvent([
                'subscription' => $subscription
            ]));
        }

        return Craft::$app->getElements()->saveElement($subscription);
    }

    /**
     * Receive a payment for a subscription
     *
     * @param Subscription $subscription
     * @param SubscriptionPayment $payment
     * @param DateTime $paidUntil
     * @return bool
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function receivePayment(Subscription $subscription, SubscriptionPayment $payment, DateTime $paidUntil): bool
    {
        if ($this->hasEventHandlers(self::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT)) {
            $this->trigger(self::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT, new SubscriptionPaymentEvent(compact('subscription', 'payment', 'paidUntil')));
        }

        $subscription->nextPaymentDate = $paidUntil;

        return Craft::$app->getElements()->saveElement($subscription);
    }
}
