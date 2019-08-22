<?php
namespace craft\commerce\events;

use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\events\CancelableEvent;

class ModifyShippingPriceEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    public $order;
    public $shippingRule;
    public $amount;
}
