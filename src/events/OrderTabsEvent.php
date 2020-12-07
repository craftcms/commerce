<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * Class OrderTabsEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class OrderTabsEvent extends Event
{
    /**
     * @var []
     */
    public $tabs;
}
