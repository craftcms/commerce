<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;

/**
 * A method all adjusters must implement
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface AdjusterInterface
{
    /**
     * Returns adjustments to add to the order
     *
     * @param Order $order
     * @return OrderAdjustment[]
     */
    public function adjust(Order $order): array;
}
