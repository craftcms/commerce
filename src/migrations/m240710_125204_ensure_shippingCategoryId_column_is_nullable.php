<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m240710_125204_ensure_shippingCategoryId_column_is_nullable migration.
 */
class m240710_125204_ensure_shippingCategoryId_column_is_nullable extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%commerce_purchasables_stores}}', 'shippingCategoryId', $this->integer()->null());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240710_125204_ensure_shippingCategoryId_column_is_nullable cannot be reverted.\n";
        return false;
    }
}
