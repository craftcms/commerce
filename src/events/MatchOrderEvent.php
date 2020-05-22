<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\commerce\models\Discount;
use craft\events\CancelableEvent;

/**
 * Class MatchOrderEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.5
 */
class MatchOrderEvent extends CancelableEvent
{
    /**
     * @var Order The matched order.
     */
    public $order;

    /**
     * @var Discount The discount that matched.
     */
    public $discount;
}
