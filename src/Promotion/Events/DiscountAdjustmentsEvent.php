<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Promotion\Data\Discount;
use yii\base\Event;

class DiscountAdjustmentsEvent extends Event
{
    use ValidatableEvent;

    public function __construct(
        public Order $order,
        public Discount $discount,
        public array $adjustments,
    ) {
    }
}
