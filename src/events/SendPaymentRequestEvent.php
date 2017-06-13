<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use yii\base\Event;

class SendPaymentRequestEvent extends Event
{
    /**
     * @var mixed Request data
     */
    public $requestData;

    /**
     * @var mixed Modified request data
     */
    public $modifiedRequestData;
}
