<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * ModifyCartInfoEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class ModifyCartInfoEvent extends Event
{
    /**
     * @var array The cart as an array
     */
    public $cartInfo = [];
}
