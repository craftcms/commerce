<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Cms\User\Elements\User;
use yii\base\Event;

class UpdatePrimaryPaymentSourceEvent extends Event
{
    public function __construct(
        public User $customer,
        public ?int $previousPrimaryPaymentSourceId = null,
        public ?int $newPrimaryPaymentSourceId = null,
    ) {
    }
}
