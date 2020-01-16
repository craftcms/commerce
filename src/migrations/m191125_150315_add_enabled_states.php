<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191125_150315_add_enabled_states migration.
 */
class m191125_150315_add_enabled_states extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_states}}', 'enabled', $this->boolean()->notNull()->defaultValue(true));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191125_150315_add_enabled_states cannot be reverted.\n";
        return false;
    }
}
