<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\models\InventoryItem;
use craft\commerce\Plugin;

/**
 * Inventory Item Trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
trait InventoryItemTrait
{
    /**
     * @var int|null The inventory item ID
     */
    public ?int $inventoryItemId = null;

    /**
     * @var InventoryItem|null The inventory item
     * @see getInventoryItem()
     * @see setInventoryItem()
     */
    private ?InventoryItem $_inventoryItem = null;

    /**
     * @param InventoryItem|null $inventoryItem
     * @return void
     */
    public function setInventoryItem(?InventoryItem $inventoryItem): void
    {
        $this->_inventoryItem = $inventoryItem;
        $this->inventoryItemId = $inventoryItem?->id ?? null;
    }

    /**
     * @return InventoryItem|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getInventoryItem(): ?InventoryItem
    {
        if (isset($this->_inventoryItem)) {
            return $this->_inventoryItem;
        }

        if ($this->inventoryItemId) {
            $this->_inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($this->inventoryItemId);

            return $this->_inventoryItem;
        }

        return null;
    }
}
