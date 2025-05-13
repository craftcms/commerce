<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Attribute\Group;
use Codeception\Test\Unit;
use craft\commerce\Plugin;

/**
 * InventoryMovementTest.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 *
 */
#[Group('inventory')]
class InventoryMovementTest extends Unit
{
    public function testGetInventoryItems()
    {
        $inventory = Plugin::getInstance()->getInventory();
    }
}
