<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\elements\Subscription;
use craft\commerce\errors\NotImplementedException;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SwitchPlansForm;

/**
 * Class Subscription Gateway
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read Plan $planModel
 * @property-read CancelSubscriptionForm $cancelSubscriptionFormModel
 * @property-read SwitchPlansForm $switchPlansFormModel
 * @property-read SubscriptionForm $subscriptionFormModel
 */
abstract class SubscriptionGateway extends Gateway implements SubscriptionGatewayInterface
{
    /**
     * Returns the cancel subscription form HTML
     *
     * @param Subscription $subscription the subscription to cancel
     */
    abstract public function getCancelSubscriptionFormHtml(Subscription $subscription): string;

    /**
     * Returns the cancel subscription form model
     */
    abstract public function getCancelSubscriptionFormModel(): CancelSubscriptionForm;

    /**
     * Returns the subscription plan settings HTML
     *
     * @param array $params
     * @return string|null
     */
    abstract public function getPlanSettingsHtml(array $params = []): ?string;

    /**
     * Returns the subscription plan model.
     */
    abstract public function getPlanModel(): Plan;

    /**
     * Returns the subscription form model
     */
    abstract public function getSubscriptionFormModel(): SubscriptionForm;

    /**
     * Returns the html form to use when switching between two plans
     */
    public function getSwitchPlansFormHtml(PlanInterface $originalPlan, PlanInterface $targetPlan): string
    {
        return '';
    }

    /**
     * Returns the form model used for switching plans.
     */
    abstract public function getSwitchPlansFormModel(): SwitchPlansForm;

    /**
     * @inheritdoc
     */
    public function reactivateSubscription(Subscription $subscription): SubscriptionResponseInterface
    {
        throw new NotImplementedException('This gateway has not implemented subscription reactivation');
    }

    /**
     * @inheritdoc
     */
    public function refreshPaymentHistory(Subscription $subscription): void
    {
    }
}
