<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m180216_130000_rename_store_location migration.
 */
class m180216_130000_rename_store_location extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        MigrationHelper::renameColumn('{{%commerce_addresses}}', 'stockLocation', 'storeLocation');
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180216_130000_rename_store_location cannot be reverted.\n";
        return false;
    }
}
