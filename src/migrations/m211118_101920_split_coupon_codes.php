<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m211118_101920_split_coupon_codes migration.
 */
class m211118_101920_split_coupon_codes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName('{{%commerce_coupons}}');
        $table = $schema->getTableSchema($rawTableName);

        if (!$table) {
            $this->createTable('{{%commerce_coupons}}', [
                'id' => $this->primaryKey(),
                'code' => $this->string(),
                'discountId' => $this->integer()->notNull(),
                'uses' => $this->integer()->notNull()->defaultValue(0),
                'maxUses' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%commerce_coupons}}', 'discountId', false);
            $this->createIndex(null, '{{%commerce_coupons}}', 'code', true);

            $this->addForeignKey(null, '{{%commerce_coupons}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m211118_101920_split_coupon_codes cannot be reverted.\n";
        return false;
    }
}
