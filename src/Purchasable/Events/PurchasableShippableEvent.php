<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Events;

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use yii\base\Event;

class PurchasableShippableEvent extends Event
{
    public function __construct(
        public PurchasableInterface $purchasable,
        public bool $isShippable,
        public ?Order $order = null,
        public ?User $currentUser = null,
    ) {
    }
}
