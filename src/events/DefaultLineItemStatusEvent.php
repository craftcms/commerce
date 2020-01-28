<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\LineItem;
use craft\commerce\models\LineItemStatus;
use yii\base\Event;

/**
 * Class DefaultLineItemStatusEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DefaultLineItemStatusEvent extends Event
{
    /**
     * @var LineItemStatus The default line item status based on the line item
     */
    public $lineItemStatus;

    /**
     * @var LineItem The line item used to determine the line item status.
     */
    public $lineItem;
}
