<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m240715_045506_drop_available_if_exists migration.
 */
class m240715_045506_drop_available_if_exists extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // drop availableForPurchase from commerce_donations if exists
        if ($this->db->columnExists('{{%commerce_donations}}', 'availableForPurchase')) {
            $this->dropColumn('{{%commerce_donations}}', 'availableForPurchase');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240715_045506_drop_available_if_exists cannot be reverted.\n";
        return false;
    }
}
