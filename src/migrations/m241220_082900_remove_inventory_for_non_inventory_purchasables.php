<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\elements\Donation;
use craft\db\Migration;
use craft\db\Table as CraftTable;

/**
 * m241220_082900_remove_inventory_for_non_inventory_purchasables migration.
 */
class m241220_082900_remove_inventory_for_non_inventory_purchasables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $purchasables = (new \craft\db\Query())
            ->select(['items.id AS id', 'elements.type AS type'])
            ->from(['items' => Table::INVENTORYITEMS])
            ->leftJoin(['elements' => CraftTable::ELEMENTS], '[[items.purchasableId]] = [[elements.id]]')
            ->all();

        // Only remove the donation inventory items that shouldn't be there, can do others later.
        foreach ($purchasables as $purchasable) {
            if (is_subclass_of($purchasable['type'], Donation::class)) {
                if (!$purchasable['type']::hasInventory()) { // should always be false, but just in case
                    $this->delete(Table::INVENTORYITEMS, ['id' => $purchasable['id']]);
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241220_082900_remove_inventory_for_non_inventory_purchasables cannot be reverted.\n";
        return false;
    }
}
