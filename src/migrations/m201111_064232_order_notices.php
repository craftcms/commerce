<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m201111_064232_order_notices migration.
 */
class m201111_064232_order_notices extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->_tableExists('{{%commerce_ordernotices}}')) {
            $this->createTable('{{%commerce_ordernotices}}', [
                'id' => $this->primaryKey(),
                'orderId' => $this->integer()->notNull(),
                'attribute' => $this->string(),
                'message' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, Table::ORDERNOTICES, 'orderId', false);
            $this->addForeignKey(null, Table::ORDERNOTICES, ['orderId'], Table::ORDERS, ['id'], 'CASCADE');
        }
    }

    private function _tableExists(string $tableName): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName($tableName);
        $table = $schema->getTableSchema($rawTableName);

        return (bool)$table;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201111_064232_order_notices cannot be reverted.\n";
        return false;
    }
}
