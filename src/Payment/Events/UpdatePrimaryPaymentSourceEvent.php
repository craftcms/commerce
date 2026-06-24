<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Cms\User\Elements\User;

class UpdatePrimaryPaymentSourceEvent
{
    public ?int $previousPrimaryPaymentSourceId = null;
    public ?int $newPrimaryPaymentSourceId = null;
    public User $customer;
}
