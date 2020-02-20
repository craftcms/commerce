<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\Plan;
use yii\base\Event;

/**
 * Class PlanEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PlanEvent extends Event
{
    /**
     * @var Plan Plan
     */
    public $plan;
}
