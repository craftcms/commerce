<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\events\CancelableEvent;

class MatchLineItemEvent extends CancelableEvent
{
    /**
     * @var LineItem The matched line item.
     */
    public $lineItem;

    /**
     * @var Discount The discount that matched.
     */
    public $discount;
}
