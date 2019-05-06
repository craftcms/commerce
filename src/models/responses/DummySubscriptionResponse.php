<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\responses;

use craft\commerce\base\SubscriptionResponseInterface;
use craft\helpers\StringHelper;

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
    private $_isCanceled = false;

    /**
     * @var int Amount of trial days
     */
    private $_trialDays = 0;

    /**
     * @inheritdoc
     */
    public function setIsCanceled(bool $isCanceled)
    {
        $this->_isCanceled = $isCanceled;
    }

    /**
     * @inheritdoc
     */
    public function setTrialDays(int $trialDays)
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
    public function getNextPaymentDate(): \DateTime
    {
        return (new \DateTime())->add(new \DateInterval('P1Y'));
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
