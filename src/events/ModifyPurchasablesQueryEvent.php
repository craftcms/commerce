<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\elements\Order;
use craft\db\Query;
use yii\base\Event;

/**
 * ModifyPurchasablesQueryEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3.0
 */
class ModifyPurchasablesQueryEvent extends Event
{
    /**
     * @var Query
     */
    public Query $query;

    /**
     * @var string|null The search term that is being used in the query, if any
     */
    public ?string $search = null;
}
