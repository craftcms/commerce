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
 */
abstract class SubscriptionGateway extends Gateway implements SubscriptionGatewayInterface
{
    /**
     * Returns the cancel subscription form HTML
     *
     * @param Subscription $subscription the subscription to cancel
     *
     * @return string
     */
    abstract public function getCancelSubscriptionFormHtml(Subscription $subscription): string;

    /**
     * Returns the cancel subscription form model
     *
     * @return CancelSubscriptionForm
     */
    abstract public function getCancelSubscriptionFormModel(): CancelSubscriptionForm;

    /**
     * Returns the subscription plan settings HTML
     *
     * @param array $params
     * @return string|null
     */
    abstract public function getPlanSettingsHtml(array $params = []);

    /**
     * Returns the subscription plan model.
     *
     * @return Plan
     */
    abstract public function getPlanModel(): Plan;

    /**
     * Returns the subscription form model
     *
     * @return SubscriptionForm
     */
    abstract public function getSubscriptionFormModel(): SubscriptionForm;

    /**
     * Returns the html form to use when switching between two plans
     *
     * @param PlanInterface $originalPlan
     * @param PlanInterface $targetPlan
     * @return string
     */
    public function getSwitchPlansFormHtml(PlanInterface $originalPlan, PlanInterface $targetPlan): string
    {
        return '';
    }

    /**
     * Returns the form model used for switching plans.
     *
     * @return SwitchPlansForm
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
    public function refreshPaymentHistory(Subscription $subscription)
    {
    }
}
