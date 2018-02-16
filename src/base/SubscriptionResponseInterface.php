<?php

namespace craft\commerce\base;

/**
 * This interface class functions that a Subscription response needs to implement
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
interface SubscriptionResponseInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Get the response data.
     *
     * @return mixed
     */
    public function getData();

    /**
     * Get the subscription reference.
     *
     * @return string
     */
    public function getReference(): string;

    /**
     * Get the number of trial days on the subscription.
     *
     * @return int
     */
    public function getTrialDays(): int;

    /**
     * Get the time of next payment.
     *
     * @return \DateTime
     */
    public function getNextPaymentDate(): \DateTime;

    /**
     * Whether the subscription is canceled.
     *
     * @return bool
     */
    public function isCanceled(): bool;

    /**
     * Whether the subscription is scheduled to be canceled.
     *
     * @return bool
     */
    public function isScheduledForCancelation(): bool;
}
