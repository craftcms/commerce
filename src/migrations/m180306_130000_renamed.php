<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180306_130000_renamed migration.
 */
class m180306_130000_renamed extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->columnExists('{{%commerce_variants}}', 'unlimitedStock')) {
            $this->renameColumn('{{%commerce_variants}}', 'unlimitedStock', 'hasUnlimitedStock');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180306_130000_renamed cannot be reverted.\n";
        return false;
    }
}
