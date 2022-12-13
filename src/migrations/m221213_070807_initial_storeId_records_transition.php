<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m221213_070807_initial_storeId_records_transition migration.
 */
class m221213_070807_initial_storeId_records_transition extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        $primaryStoreId = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_stores}}'])
            ->where(['primary' => true])
            ->scalar();

        if(!$this->db->columnExists('{{%commerce_paymentcurrencies}}', 'storeId')) {
            $this->addColumn('{{%commerce_paymentcurrencies}}', 'storeId',  $this->integer()->after('id')->defaultValue($primaryStoreId)->notNull());
            $this->addForeignKey(null, '{{%commerce_paymentcurrencies}}', ['storeId'], '{{%commerce_stores}}', ['id'], 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%commerce_paymentcurrencies}}', ['storeId'], false);
        }

        if(!$this->db->columnExists('{{%commerce_donations}}', 'storeId')) {
            $this->addColumn('{{%commerce_donations}}', 'storeId',  $this->integer()->after('id')->defaultValue($primaryStoreId)->notNull());
            $this->addForeignKey(null, '{{%commerce_donations}}', ['storeId'], '{{%commerce_stores}}', ['id'], 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%commerce_donations}}', ['storeId'], false);
        }

        if(!$this->db->columnExists('{{%commerce_discounts}}', 'storeId')) {
            $this->addColumn('{{%commerce_discounts}}', 'storeId',  $this->integer()->after('id')->defaultValue($primaryStoreId)->notNull());
            $this->addForeignKey(null, '{{%commerce_discounts}}', ['storeId'], '{{%commerce_stores}}', ['id'], 'CASCADE', 'CASCADE');
            $this->createIndex(null, '{{%commerce_discounts}}', ['storeId'], false);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221213_070807_initial_storeId_records_transition cannot be reverted.\n";
        return false;
    }
}
