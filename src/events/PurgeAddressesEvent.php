<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\db\Query;
use craft\events\CancelableEvent;

/**
 * Class PurgeAddressesEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 */
class PurgeAddressesEvent extends CancelableEvent
{
    /**
     * @var Query|null The query to get the purgeable addresses
     */
    public ?Query $addressesQuery;
}
