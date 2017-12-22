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
     * Whether this gateway supports creating, updating and deleting plans on the gateway.
     *
     * @return bool
     */
    public function supportsPlanOperations(): bool;

    /**
     * Delete a subscription plan on the gateway.
     *
     * @param string $reference the plan reference.
     *
     * @return mixed
     */
    public function deletePlan(string $reference);
}
