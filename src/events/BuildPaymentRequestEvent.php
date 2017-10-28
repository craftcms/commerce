<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use yii\base\Event;

class BuildPaymentRequestEvent extends Event
{
    // Properties
    // =============================================================================

    /**
     * @var array Request params
     */
    public $params;
}
