<?php

namespace craft\commerce\base;

use craft\base\SavableComponentInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\elements\User;
use craft\web\Response as WebResponse;

/**
 * PlanInterface defines the common interface to be implemented by plan classes.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
interface PlanInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Return a friendly name of the gateway subscription plan.
     *
     * @return string
     */
    public function getFriendlyPlanName(): string;
}
