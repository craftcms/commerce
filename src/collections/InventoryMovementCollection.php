<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\collections;

use craft\commerce\base\InventoryMovement;
use craft\commerce\base\InventoryMovementInterface;
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
    /**
     * @return array
     * @since 5.3.2
     */
    public function getPurchasables(): array
    {
        return $this->map(function(InventoryMovementInterface $updateInventoryLevel) {
            return $updateInventoryLevel->getInventoryItem()->getPurchasable();
        })->all();
    }
}
