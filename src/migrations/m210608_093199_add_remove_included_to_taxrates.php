<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use Craft;

/**
 * m210608_093199_add_remove_included_to_taxrates migration.
 */
class m210608_093199_add_remove_included_to_taxrates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%commerce_taxrates}}';
        if (!$this->db->columnExists($table, 'removeIncluded')) {
            $this->addColumn($table, 'removeIncluded', $this->boolean()->after('include'));
        }

        // Wherever we were currently doing an included tax, turn on removeIncluded
        $columns = ['removeIncluded' => true,];
        $condition = ['include' => true];
        $this->update($table, $columns, $condition, [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210608_093199_add_remove_included_to_taxrates cannot be reverted.\n";
        return false;
    }
}