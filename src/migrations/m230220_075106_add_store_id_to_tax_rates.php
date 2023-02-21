<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230220_075106_add_store_id_to_tax_rates migration.
 */
class m230220_075106_add_store_id_to_tax_rates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::TAXRATES, 'storeId', $this->integer());

        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->scalar();

        $this->update(Table::TAXRATES, ['storeId' => $primaryStoreId], ['storeId' => null], [], false);

        $this->addForeignKey(null, Table::TAXRATES, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::TAXRATES, ['storeId'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230220_075106_add_store_id_to_tax_rates cannot be reverted.\n";
        return false;
    }
}
