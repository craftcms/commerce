<?php

namespace craft\commerce\migrations;

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
        $this->addColumn('{{%commerce_producttypes}}', 'previewTargets', $this->json()->after('propagationMethod'));

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
