<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Adjuster\Contracts;

use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Elements\Order;

interface AdjusterInterface
{
    /**
     * Returns adjustments to add to the order.
     *
     * @return OrderAdjustment[]
     */
    public function adjust(Order $order): array;
}
