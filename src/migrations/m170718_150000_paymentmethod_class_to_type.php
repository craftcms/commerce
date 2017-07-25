<?php

namespace craft\commerce\migrations;

use craft\commerce\records\Gateway;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m170718_150000_paymentmethod_class_to_type
 */
class m170718_150000_paymentmethod_class_to_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        MigrationHelper::renameColumn(Gateway::tableName(), 'class', 'type', $this);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170718_150000_paymentmethod_class_to_type cannot be reverted.\n";


        return false;
    }
}
