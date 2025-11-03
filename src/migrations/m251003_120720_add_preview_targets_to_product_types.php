<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m251003_120720_add_preview_targets_to_product_types migration.
 */
class m251003_120720_add_preview_targets_to_product_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // add previewTargets column to commerce_producttypes table
        if (!$this->db->columnExists(Table::PRODUCTTYPES, 'previewTargets')) {
            $this->addColumn(Table::PRODUCTTYPES, 'previewTargets', $this->json()->after('propagationMethod'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m251003_120720_add_preview_targets_to_product_types cannot be reverted.\n";
        return false;
    }
}
