<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Contracts;

use DateTime;

interface SubscriptionResponseInterface
{
    public function getData(): mixed;

    public function getReference(): string;

    public function getTrialDays(): int;

    public function getNextPaymentDate(): DateTime;

    public function isCanceled(): bool;

    public function isScheduledForCancellation(): bool;

    public function isInactive(): bool;
}
