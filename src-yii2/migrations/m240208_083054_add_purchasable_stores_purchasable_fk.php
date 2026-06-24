<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m240208_083054_add_purchasable_stores_purchasable_fk migration.
 */
class m240208_083054_add_purchasable_stores_purchasable_fk extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        // all purchasables in purchasable_stores table that is not in the purchasables table query:
        $subQuery = (new Query())
            ->select('id')
            ->from('{{%commerce_purchasables}}');

        $purchasables = (new Query())
            ->select('purchasableId')
            ->from('{{%commerce_purchasables_stores}}')
            ->where(['not in', 'purchasableId', $subQuery])
            ->column($this->db); // Assuming $this->db is your database connection

        // delete all purchasables in purchasable_stores table that is not in the purchasables table
        $this->delete('{{%commerce_purchasables_stores}}', ['in', 'purchasableId', $purchasables]);

        $this->addForeignKey(null, '{{%commerce_purchasables_stores}}', ['purchasableId'], '{{%commerce_purchasables}}', ['id'],'CASCADE', 'CASCADE');


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240208_083054_add_purchasable_stores_purchasable_fk cannot be reverted.\n";
        return false;
    }
}
