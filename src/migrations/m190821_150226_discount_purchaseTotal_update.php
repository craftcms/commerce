<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190821_150226_discount_purchaseTotal_update migration.
 */
class m190821_150226_discount_purchaseTotal_update extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%commerce_discounts}}', 'purchaseTotal', $this->decimal(14, 4));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190821_150226_discount_purchaseTotal_update cannot be reverted.\n";
        return false;
    }
}
