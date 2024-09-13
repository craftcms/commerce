<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\collections;

use craft\commerce\models\inventory\UpdateInventoryLevel;
use Illuminate\Support\Collection;

/**
 * UpdateInventoryLevelCollection represents a collection of UpdateInventoryLevel models.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class UpdateInventoryLevelCollection extends Collection
{
    /**
     * Creates a UpdateInventoryLevelCollection from an array of UpdateInventoryLevel attributes.
     *
     * @param array $items
     * @return static
     */
    public static function make($items = [])
    {
        foreach ($items as &$item) {
            if ($item instanceof UpdateInventoryLevel) {
                continue;
            }

            $item = \Craft::createObject(UpdateInventoryLevel::class, [
                'config' => ['attributes' => $item],
            ]);
        }

        /** @var static $collection */
        $collection = parent::make($items);
        return $collection;
    }

    /**
     * @return array
     */
    public function getPurchasables(): array
    {
        return $this->map(function(UpdateInventoryLevel $updateInventoryLevel) {
            return $updateInventoryLevel->inventoryItem->getPurchasable();
        })->all();
    }
}
