<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m220817_135050_add_purchase_total_back_if_missing migration.
 */
class m220817_135050_add_purchase_total_back_if_missing extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_discounts}}', 'purchaseTotal')) {
            $this->addColumn('{{%commerce_discounts}}', 'purchaseTotal', $this->decimal(14, 4)->notNull()->defaultValue(0));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220817_135050_add_purchase_total_back_if_missing cannot be reverted.\n";
        return false;
    }
}
