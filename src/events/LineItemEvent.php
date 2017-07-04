<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\base\Purchasable;
use craft\commerce\models\LineItem;
use yii\base\Event;

class LineItemEvent extends Event
{
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
