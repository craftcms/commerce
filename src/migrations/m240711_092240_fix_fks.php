<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240711_092240_fix_fks migration.
 */
class m240711_092240_fix_fks extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists('{{%commerce_catalogpricingrules}}', 'purchasableId');
        $this->dropIndexIfExists('{{%commerce_catalogpricingrules}}', 'purchasableId');

        if ($this->db->columnExists('{{%commerce_catalogpricingrules}}', 'purchasableId')) {
            $this->dropColumn('{{%commerce_catalogpricingrules}}', 'purchasableId');
        }

        // Fix constraint to set not null on delete
        $this->dropForeignKeyIfExists('{{%commerce_purchasables_stores}}', 'shippingCategoryId');
        $this->addForeignKey(null, '{{%commerce_purchasables_stores}}', ['shippingCategoryId'], Table::SHIPPINGCATEGORIES, ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240711_092240_fix_fks cannot be reverted.\n";
        return false;
    }
}
