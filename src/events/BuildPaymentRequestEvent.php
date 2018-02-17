<?php

namespace craft\commerce\events;

use yii\base\Event;

/**
 * Class BuildPaymentRequestEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BuildPaymentRequestEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var array Request params
     */
    public $params;
}
