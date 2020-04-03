<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\TaxEngineInterface;
use yii\base\Event;

/**
 * Class TaxEngineEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class TaxEngineEvent extends Event
{
    /**
     * @var TaxEngineInterface The tax engine
     */
    public $engine;
}
