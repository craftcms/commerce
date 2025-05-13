<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Store;
use craft\events\CancelableEvent;

/**
 * Store event class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class StoreEvent extends CancelableEvent
{
    /**
     * @var Store The store model associated with the event.
     */
    public Store $store;

    /**
     * @var bool Whether the store is brand new
     */
    public bool $isNew = false;
}
