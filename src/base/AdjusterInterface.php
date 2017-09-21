<?php

namespace craft\commerce\base;

use craft\commerce\elements\Order;

/**
 * A method all adjusters must implement
 *
 * Interface AdjusterInterface
 *
 * @package Commerce\Adjusters
 */
interface AdjusterInterface
{
    /**
     * The adjust method returns adjustents to add to the order
     *
     * @param Order $order
     *
     * @return \craft\commerce\models\OrderAdjustment[]
     */
    public function adjust(Order $order);
}
