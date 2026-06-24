<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Responses;

use craft\commerce\base\SubscriptionResponseInterface;
use CraftCms\Cms\Support\Str;
use DateInterval;
use DateTime;

class DummySubscriptionResponse implements SubscriptionResponseInterface
{
    private bool $_isCanceled = false;

    private int $_trialDays = 0;

    public function setIsCanceled(bool $isCanceled): void
    {
        $this->_isCanceled = $isCanceled;
    }

    public function setTrialDays(int $trialDays): void
    {
        $this->_trialDays = $trialDays;
    }

    #[\Override]
    public function getData(): mixed
    {
        return ['dummyData' => Str::random()];
    }

    #[\Override]
    public function getReference(): string
    {
        return Str::random();
    }

    #[\Override]
    public function getTrialDays(): int
    {
        return $this->_trialDays;
    }

    #[\Override]
    public function getNextPaymentDate(): DateTime
    {
        return new DateTime()->add(new DateInterval('P1Y'));
    }

    #[\Override]
    public function isCanceled(): bool
    {
        return $this->_isCanceled;
    }

    #[\Override]
    public function isScheduledForCancellation(): bool
    {
        return $this->_isCanceled;
    }

    #[\Override]
    public function isInactive(): bool
    {
        return false;
    }
}
