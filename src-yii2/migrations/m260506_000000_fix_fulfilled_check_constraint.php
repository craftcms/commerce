<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m260506_000000_fix_fulfilled_check_constraint migration.
 */
class m260506_000000_fix_fulfilled_check_constraint extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // On PostgreSQL, renaming a table does not rename its check constraints. The
        // commerce_inventorytransactions table (originally commerce_inventorymovements) may
        // still have a stale constraint that excludes 'fulfilled'. Drop both the old and
        // previously-added constraint names and re-add the definitive one.
        if ($this->db->getIsPgsql()) {
            $tableNameQuoted = $this->db->quoteTableName('{{%commerce_inventorytransactions}}');
            $typeColumnQuoted = $this->db->quoteColumnName('type');

            // Old constraint: auto-named by PostgreSQL when the table was created as commerce_inventorymovements
            $oldConstraint = $this->db->getSchema()->getRawTableName('{{%commerce_inventorymovements}}') . '_type_check';
            // New constraint: added by the previous alterColumn migration (m240228_060604)
            $newConstraint = 'commerce_inventorytransactions_type_check';

            foreach ([$oldConstraint, $newConstraint] as $constraint) {
                $this->db->createCommand(
                    "ALTER TABLE $tableNameQuoted DROP CONSTRAINT IF EXISTS " . $this->db->quoteColumnName($constraint)
                )->execute();
            }

            $this->db->createCommand(
                "ALTER TABLE $tableNameQuoted ADD CONSTRAINT commerce_inventorytransactions_type_check CHECK ($typeColumnQuoted IN ('available', 'reserved', 'damaged', 'safety', 'qualityControl', 'committed', 'fulfilled', 'incoming'))"
            )->execute();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260506_000000_fix_fulfilled_check_constraint cannot be reverted.\n";
        return false;
    }
}
