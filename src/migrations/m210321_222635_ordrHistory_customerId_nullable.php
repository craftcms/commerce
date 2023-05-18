<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m210321_222635_ordrHistory_customerId_nullable migration.
 */
class m210321_222635_ordrHistory_customerId_nullable extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        MigrationHelper::dropForeignKeyIfExists('{{%commerce_orderhistories}}', ['customerId'], $this);
        MigrationHelper::dropIndexIfExists('{{%commerce_orderhistories}}', ['customerId'], false, $this);

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_orderhistories}} alter column [[customerId]] DROP NOT NULL');
        } else {
            $this->alterColumn('{{%commerce_orderhistories}}', 'customerId', $this->integer()->null());
        }

        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['customerId'], Table::CUSTOMERS, ['id'], 'SET NULL');
        $this->createIndex(null, '{{%commerce_orderhistories}}', 'customerId', false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210321_222635_ordrHistory_customerId_nullable cannot be reverted.\n";
        return false;
    }
}
