<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\events\CancelableEvent;

/**
 * Class CartEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CartEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var LineItem The line item model.
     */
    public $lineItem;

    /**
     * @var Order The order element
     */
    public $order;
}
