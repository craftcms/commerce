<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Cms\User\Elements\User;

class UpdatePrimaryPaymentSourceEvent
{
    public function __construct(
        public User $customer,
        public ?int $previousPrimaryPaymentSourceId = null,
        public ?int $newPrimaryPaymentSourceId = null,
    ) {}
}
