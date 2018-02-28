<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\Discount;
use craft\commerce\models\LineItem;
use craft\events\CancelableEvent;

/**
 * Class MatchLineItemEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class MatchLineItemEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var LineItem The matched line item.
     */
    public $lineItem;

    /**
     * @var Discount The discount that matched.
     */
    public $discount;
}
