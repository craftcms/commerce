<?php

namespace craft\commerce\events;

use craft\commerce\models\Sale;
use craft\events\CancelableEvent;

/**
 * Class SaleMatchEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SaleMatchEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var Sale The sale
     */
    public $sale;

    /**
     * @var bool If this is a new sale
     */
    public $isNew;
}
