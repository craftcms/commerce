<?php

namespace craft\commerce\events;

use CraftCms\Cms\User\Elements\User;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Payment\Events\PrimaryPaymentSourceUpdated} instead.
 */
class UpdatePrimaryPaymentSourceEvent extends Event
{
    /**
     * @var int|null The previous primary payment source ID
     */
    public ?int $previousPrimaryPaymentSourceId = null;

    /**
     * @var int|null The new primary payment source ID
     */
    public ?int $newPrimaryPaymentSourceId = null;

    /**
     * @var User The customer
     */
    public User $customer;
}
