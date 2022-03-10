<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table as CraftTable;

/**
 * m220308_221717_orderhistory_name migration.
 */
class m220308_221717_orderhistory_name extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_orderhistories}}', 'userName')) {
            $this->addColumn('{{%commerce_orderhistories}}', 'userName', $this->string());
        }

        // Allow null
        $this->dropForeignKeyIfExists('{{%commerce_orderhistories}}', ['userId']);
        $this->dropIndexIfExists('{{%commerce_orderhistories}}', ['userId']);
        $this->alterColumn('{{%commerce_orderhistories}}', 'userId', $this->integer());
        $this->addForeignKey(null, '{{%commerce_orderhistories}}', ['userId'], '{{%elements}}', ['id'], 'SET NULL');
        $this->createIndex(null, '{{%commerce_orderhistories}}', 'userId', false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220308_221717_orderhistory_name cannot be reverted.\n";
        return false;
    }
}
