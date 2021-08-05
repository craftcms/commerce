<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

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
    /**
     * @var LineItem The line item model.
     */
    public LineItem $lineItem;

    /**
     * @var bool If this is a new line item.
     */
    public bool $isNew = false;
}
