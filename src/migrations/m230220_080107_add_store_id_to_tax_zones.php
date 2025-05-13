<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230220_080107_add_store_id_to_tax_zones migration.
 */
class m230220_080107_add_store_id_to_tax_zones extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::TAXZONES, 'storeId', $this->integer());

        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->scalar();

        $this->update(Table::TAXZONES, ['storeId' => $primaryStoreId], ['storeId' => null], [], false);

        $this->addForeignKey(null, Table::TAXZONES, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::TAXZONES, ['storeId'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230220_080107_add_store_id_to_tax_zones cannot be reverted.\n";
        return false;
    }
}
