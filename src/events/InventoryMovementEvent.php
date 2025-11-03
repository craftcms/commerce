<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\InventoryMovementInterface;
use yii\base\Event;

/**
 * InventoryMovementEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.5.0
 */
class InventoryMovementEvent extends Event
{
    /**
     * @var InventoryMovementInterface The inventory movement that was executed
     */
    public InventoryMovementInterface $inventoryMovement;
}
