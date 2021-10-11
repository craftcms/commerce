<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\responses;

use craft\commerce\base\SubscriptionResponseInterface;
use craft\helpers\StringHelper;
use DateInterval;
use DateTime;

/**
 * This is a dummy gateway request response.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DummySubscriptionResponse implements SubscriptionResponseInterface
{
    /**
     * @var bool Whether this subscription is canceled
     */
    private bool $_isCanceled = false;

    /**
     * @var int Amount of trial days
     */
    private int $_trialDays = 0;

    /**
     * @param bool $isCanceled
     */
    public function setIsCanceled(bool $isCanceled): void
    {
        $this->_isCanceled = $isCanceled;
    }

    /**
     * @param int $trialDays
     */
    public function setTrialDays(int $trialDays): void
    {
        $this->_trialDays = $trialDays;
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        return ['dummyData' => StringHelper::randomString()];
    }

    /**
     * @inheritdoc
     */
    public function getReference(): string
    {
        return StringHelper::randomString();
    }

    /**
     * @inheritdoc
     */
    public function getTrialDays(): int
    {
        return $this->_trialDays;
    }

    /**
     * @inheritdoc
     */
    public function getNextPaymentDate(): DateTime
    {
        return (new DateTime())->add(new DateInterval('P1Y'));
    }

    /**
     * @inheritdoc
     */
    public function isCanceled(): bool
    {
        return $this->_isCanceled;
    }

    /**
     * @inheritdoc
     */
    public function isScheduledForCancellation(): bool
    {
        return $this->_isCanceled;
    }

    /**
     * @inheritdoc
     */
    public function isInactive(): bool
    {
        return false;
    }
}
