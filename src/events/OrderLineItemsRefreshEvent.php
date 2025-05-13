<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * Class OrderLineItemsRefreshEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.1.0
 */
class OrderLineItemsRefreshEvent extends Event
{
    /**
     * @var array
     */
    public array $lineItems;

    public bool $recalculate = false;
}
