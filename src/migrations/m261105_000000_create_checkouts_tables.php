<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m261105_000000_create_checkouts_tables migration.
 */
class m261105_000000_create_checkouts_tables extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Table::CHECKOUTS)) {
            $this->createTable(Table::CHECKOUTS, [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'handle' => $this->string()->notNull(),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'isDefault' => $this->boolean()->notNull()->defaultValue(false),
                'sortOrder' => $this->integer(),
                'brandColor' => $this->string(),
                'colorScheme' => $this->string()->notNull()->defaultValue('light'),
                'cornerStyle' => $this->string()->notNull()->defaultValue('rounded'),
                'logoUrl' => $this->string(),
                'returnUrl' => $this->string(),
                'continueShoppingUrl' => $this->string(),
                'allowPartialPayment' => $this->boolean()->notNull()->defaultValue(false),
                'partialPaymentPercentages' => $this->text(),
                'requireBillingAddress' => $this->boolean(),
                'orderCondition' => $this->text(),
                'orderConditionMessage' => $this->string(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, Table::CHECKOUTS, 'handle', true);
        }

        if (!$this->db->tableExists(Table::CHECKOUTS_STORES)) {
            $this->createTable(Table::CHECKOUTS_STORES, [
                'checkoutId' => $this->integer()->notNull(),
                'storeId' => $this->integer()->notNull(),
                'PRIMARY KEY([[checkoutId]], [[storeId]])',
            ]);
            $this->addForeignKey(null, Table::CHECKOUTS_STORES, ['checkoutId'], Table::CHECKOUTS, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, Table::CHECKOUTS_STORES, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        }

        if (!$this->db->tableExists(Table::CHECKOUTS_GATEWAYS)) {
            $this->createTable(Table::CHECKOUTS_GATEWAYS, [
                'checkoutId' => $this->integer()->notNull(),
                'gatewayId' => $this->integer()->notNull(),
                'PRIMARY KEY([[checkoutId]], [[gatewayId]])',
            ]);
            $this->addForeignKey(null, Table::CHECKOUTS_GATEWAYS, ['checkoutId'], Table::CHECKOUTS, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, Table::CHECKOUTS_GATEWAYS, ['gatewayId'], Table::GATEWAYS, ['id'], 'CASCADE', null);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(Table::CHECKOUTS_GATEWAYS);
        $this->dropTableIfExists(Table::CHECKOUTS_STORES);
        $this->dropTableIfExists(Table::CHECKOUTS);

        return true;
    }
}
