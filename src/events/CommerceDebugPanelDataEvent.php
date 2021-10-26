<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\debug\CommercePanel;
use yii\base\Event;

/**
 * Class CommerceDebugPanelRenderEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class CommerceDebugPanelDataEvent extends Event
{
    /**
     * @var array
     */
    public array $nav;

    /**
     * @var array
     */
    public array $content;
}