<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\helpers\StringHelper;

/**
 * This is a dummy gateway request response.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DummySubscriptionResponse implements SubscriptionResponseInterface
{
    private $_isCanceled = false;

    public function setIsCanceled(bool $isCanceled)
    {
        $this->_isCanceled = $isCanceled;
    }

    public function getData()
    {
        return ['reference' => StringHelper::randomString()];
    }

    public function getReference(): string
    {
        return StringHelper::randomString();
    }

    public function getTrialDays(): int
    {
        return 0;
    }

    public function getNextPaymentDate(): \DateTime
    {
        return (new \DateTime())->add(new \DateInterval('P1Y'));
    }

    public function isCanceled(): bool
    {
        return $this->_isCanceled;
    }

    public function isScheduledForCancelation(): bool
    {
        return $this->_isCanceled;
    }

}
