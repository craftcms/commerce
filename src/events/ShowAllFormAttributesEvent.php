<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use yii\base\Event;

/**
 * ShowAllFormAttributesEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class ShowAllFormAttributesEvent extends Event
{
    /**
     * @var bool set to true to show all available attributes.
     */
    public bool $all = false;
}
