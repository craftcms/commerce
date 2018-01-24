<?php

namespace craft\commerce\base;

/**
 * Class Subscription Gateway
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
abstract class SubscriptionGateway extends Gateway implements SubscriptionGatewayInterface
{
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
}
