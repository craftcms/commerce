<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m210611_093299_add_remove_vat_included_to_taxrates migration.
 */
class m210611_093299_add_remove_vat_included_to_taxrates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = '{{%commerce_taxrates}}';
        if (!$this->db->columnExists($table, 'removeVatIncluded')) {
            $this->addColumn($table, 'removeVatIncluded', $this->boolean()->after('removeIncluded'));
        }

        // Wherever we were currently doing an included tax, turn on removeIncluded
        $columns = ['removeVatIncluded' => true,];
        $condition = ['include' => true, 'isVat' => true];
        $this->update($table, $columns, $condition, [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210611_093299_add_remove_vat_included_to_taxrates cannot be reverted.\n";
        return false;
    }
}
