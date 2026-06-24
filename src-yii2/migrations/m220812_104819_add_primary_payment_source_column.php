<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m220812_104819_add_primary_payment_source_column migration.
 */
class m220812_104819_add_primary_payment_source_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_customers}}', 'primaryPaymentSourceId')) {
            $this->addColumn('{{%commerce_customers}}', 'primaryPaymentSourceId', $this->integer()->after('primaryShippingAddressId'));
            $this->createIndex(null, '{{%commerce_customers}}', 'primaryPaymentSourceId', false);

            $this->addForeignKey(null, '{{%commerce_customers}}', ['primaryPaymentSourceId'], '{{%commerce_paymentsources}}', ['id'], 'SET NULL');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220812_104819_add_primary_payment_source_column cannot be reverted.\n";
        return false;
    }
}
