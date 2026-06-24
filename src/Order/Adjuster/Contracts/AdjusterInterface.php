<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Adjuster\Contracts;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;

interface AdjusterInterface
{
    /**
     * Returns adjustments to add to the order.
     *
     * @return OrderAdjustment[]
     */
    public function adjust(Order $order): array;
}
