<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;

/**
 * m241128_174712_fix_maxLevels_structured_productTypes migration.
 */
class m241128_174712_fix_maxLevels_structured_productTypes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get all product types from Commerce project config
        $structuredProductTypesWithMaxLevels = (new Query())
            ->from(Table::PRODUCTTYPES)
            ->where(['isStructure' => true])
            ->andWhere(['not', ['maxLevels' => null]])
            ->collect();

        // Loop through and update the `maxLevels` column in the `structures` table
        $structuredProductTypesWithMaxLevels->each(function($productType) {
            $this->update(CraftTable::STRUCTURES, ['maxLevels' => $productType['maxLevels']], ['id' => $productType['structureId']]);
        });

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241128_174712_fix_maxLevels_structured_productTypes cannot be reverted.\n";
        return false;
    }
}
