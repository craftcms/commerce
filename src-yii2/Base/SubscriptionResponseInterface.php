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
    public function getData(): mixed;

    /**
     * Returns the subscription reference.
     */
    public function getReference(): string;

    /**
     * Returns the number of trial days on the subscription.
     */
    public function getTrialDays(): int;

    /**
     * Returns the time of next payment.
     */
    public function getNextPaymentDate(): DateTime;

    /**
     * Returns whether the subscription is canceled.
     */
    public function isCanceled(): bool;

    /**
     * Returns whether the subscription is scheduled to be canceled.
     */
    public function isScheduledForCancellation(): bool;

    /**
     * Whether the subscription is unpaid.
     */
    public function isInactive(): bool;
}
