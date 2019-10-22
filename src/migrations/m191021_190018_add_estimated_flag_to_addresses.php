<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191021_190018_add_estimated_flag_to_addresses migration.
 */
class m191021_190018_add_estimated_flag_to_addresses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_addresses}}', 'isEstimated', $this->boolean()->notNull()->defaultValue(false));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191021_190018_add_estimated_flag_to_addresses cannot be reverted.\n";
        return false;
    }
}
