<?php

namespace Commerce\Adjusters;

use Craft\Commerce_OrderModel;

/**
 * A method all adjusters must implement
 *
 * Interface AdjusterInterface
 *
 * @package Commerce\Adjusters
 */
interface Commerce_AdjusterInterface
{
    /**
     * The adjust method modifies the order values (like baseShippingCost),
     * and records all adjustments by returning one or more orderAdjusterModels
     * to be saved on the order.
     *
     * @param Commerce_OrderModel $order
     * @param array               $lineItems
     *
     * @return \Craft\Commerce_OrderAdjustmentModel[]
     */
    public function adjust(Commerce_OrderModel &$order, array $lineItems = []);
}
