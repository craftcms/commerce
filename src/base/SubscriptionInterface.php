<?php

namespace craft\commerce\base;

use craft\base\SavableComponentInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;

/**
 * SubscriptionInterface defines the common interface to be implemented by gateway classes that support subscriptions.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
interface SubscriptionInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Fetch a subscription plan by its reference
     *
     * @param string $reference
     *
     * @return string
     */
    public function getSubscriptionPlanByReference(string $reference): string;

    /**
     * Get all subscription plans as array containing hashes with `reference` and `name` as keys.
     *
     * @return array
     */
    public function getSubscriptionPlans(): array;

}
