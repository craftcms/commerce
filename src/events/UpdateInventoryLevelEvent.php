<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\models\inventory\UpdateInventoryLevel;
use yii\base\Event;

/**
 * UpdateInventoryLevelEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.5.0
 */
class UpdateInventoryLevelEvent extends Event
{
    /**
     * @var UpdateInventoryLevel The inventory level update that was executed
     */
    public UpdateInventoryLevel $updateInventoryLevel;
}
