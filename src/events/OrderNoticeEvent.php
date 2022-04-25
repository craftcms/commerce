<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\OrderNotice;
use craft\events\CancelableEvent;

/**
 * Class OrderNoticeEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.4.x
 */
class OrderNoticeEvent extends CancelableEvent
{
    /**
     * @var OrderNotice The line item model.
     */
    public $orderNotice;
}
