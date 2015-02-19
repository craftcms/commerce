<?php

namespace Market\Adjusters;
use Craft\Market_OrderAdjustmentModel;
use Craft\Market_OrderModel;

/**
 * A method all adjusters must implement
 *
 * Interface AdjusterInterface
 * @package Market\Adjusters
 */
interface Market_AdjusterInterface
{
    /**
     * @param Market_OrderModel $order
     * @return Market_OrderAdjustmentModel[]
     */
    public function adjust(Market_OrderModel &$order);
}
