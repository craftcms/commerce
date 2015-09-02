<?php

namespace Market\Adjusters;

use Craft\Market_OrderModel;

/**
 * A method all adjusters must implement
 *
 * Interface AdjusterInterface
 *
 * @package Market\Adjusters
 */
interface Market_AdjusterInterface
{
	/**
	 * The adjust method modifies the order values (like baseShippingCost),
	 * and records all adjustments by returning one or more orderAdjusterModels
	 * to be saved on the order.
	 *
	 * @param Market_OrderModel $order
	 * @param array             $lineItems
	 *
	 * @return \Craft\Market_OrderAdjustmentModel[]
	 */
	public function adjust(Market_OrderModel &$order, array $lineItems = []);
}
