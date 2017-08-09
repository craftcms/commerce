<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\models\Sale;
use craft\events\CancelableEvent;

class SaleMatchEvent extends CancelableEvent
{
    /**
     * @var Sale The sale
     */
    public $sale;

    /**
     * @var bool If this is a new sale
     */
    public $isNew;
}
