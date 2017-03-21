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
     * The adjust method modifies the order values (like baseShippingCost),
     * and records all adjustments by returning one or more orderAdjusterModels
     * to be saved on the order.
     *
     * @param Order $order
     * @param array $lineItems
     *
     * @return \craft\commerce\models\OrderAdjustment[]
     */
    public function adjust(Order &$order, array $lineItems = []);
}
