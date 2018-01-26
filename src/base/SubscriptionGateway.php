<?php

namespace craft\commerce\base;

use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionForm;

/**
 * Class Subscription Gateway
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
abstract class SubscriptionGateway extends Gateway implements SubscriptionGatewayInterface
{
    /**
     * Get the cancel subscription form model
     *
     * @return CancelSubscriptionForm
     */
    abstract public function getCancelSubscriptionFormModel(): CancelSubscriptionForm;

    /**
     * Subscription plan settings HTML
     *
     * @param array $params
     *
     * @return string|null
     */
    abstract public function getPlanSettingsHtml(array $params = []);

    /**
     * Get the subscription plan model.
     *
     * @return Plan
     */
    abstract public function getPlanModel(): Plan;

    /**
     * Get the subscription form model
     *
     * @return SubscriptionForm
     */
    abstract public function getSubscriptionFormModel(): SubscriptionForm;
}
