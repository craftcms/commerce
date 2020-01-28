<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use DateTime;

/**
 * This interface class functions that a Subscription response needs to implement
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface SubscriptionResponseInterface
{
    /**
     * Returns the response data.
     *
     * @return mixed
     */
    public function getData();

    /**
     * Returns the subscription reference.
     *
     * @return string
     */
    public function getReference(): string;

    /**
     * Returns the number of trial days on the subscription.
     *
     * @return int
     */
    public function getTrialDays(): int;

    /**
     * Returns the time of next payment.
     *
     * @return DateTime
     */
    public function getNextPaymentDate(): DateTime;

    /**
     * Returns whether the subscription is canceled.
     *
     * @return bool
     */
    public function isCanceled(): bool;

    /**
     * Returns whether the subscription is scheduled to be canceled.
     *
     * @return bool
     */
    public function isScheduledForCancellation(): bool;

    /**
     * Whether the subscription is unpaid.
     *
     * @return bool
     */
    public function isInactive(): bool;
}
