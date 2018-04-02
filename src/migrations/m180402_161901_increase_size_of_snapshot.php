<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180402_161901_increase_size_of_snapshot migration.
 */
class m180402_161901_increase_size_of_snapshot extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Make fields larger to store more data

        $this->alterColumn('{{%commerce_lineitems}}', 'snapshot', $this->longText());
        $this->alterColumn('{{%commerce_orderadjustments}}', 'sourceSnapshot', $this->longText());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180402_161901_increase_size_of_snapshot cannot be reverted.\n";
        return false;
    }
}
