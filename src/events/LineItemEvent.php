<?php

namespace craft\commerce\events;

use craft\commerce\base\Purchasable;
use craft\commerce\models\LineItem;
use yii\base\Event;

/**
 * Class LineItemEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var LineItem The line item model.
     */
    public $lineItem;

    /**
     * @var Purchasable Purchasable for this line item, if line item was populated from a purchasable
     */
    public $purchasable;

    /**
     * @var bool If this is a new line item.
     */
    public $isNew;
}
