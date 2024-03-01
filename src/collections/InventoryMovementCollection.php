<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\collections;

use craft\commerce\base\InventoryMovement;
use Illuminate\Support\Collection;

/**
 * InventoryMovementCollection represents a collection of InventoryMovementInterface models.
 *
 * @template TValue of InventoryMovement
 * @extends Collection<array-key, TValue>
 * @method static self make($items = [])
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class InventoryMovementCollection extends Collection
{
}
