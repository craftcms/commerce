<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\db\Query;
use craft\events\CancelableEvent;
use yii\base\Event;

/**
 * Class PurgeAddressesEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class PurgeAddressesEvent extends CancelableEvent
{
    /**
     * @var Query|null The query to get the purgable addresses
     */
    public $addressesQuery;
}
