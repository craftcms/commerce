<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m240220_045806_product_versioning migration.
 */
class m240220_045806_product_versioning extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::PRODUCTTYPES, 'enableVersioning', $this->boolean()->defaultValue(false)->notNull()->after('handle'));
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240220_045806_product_versioning cannot be reverted.\n";
        return false;
    }
}
