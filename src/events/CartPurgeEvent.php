<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\db\Query;
use craft\events\CancelableEvent;

/**
 * Class CartPurgeEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3
 */
class CartPurgeEvent extends CancelableEvent
{
    /**
     * @var Query The query that identifies the order IDs to be purged.
     */
    public Query $inactiveCartsQuery;
}
