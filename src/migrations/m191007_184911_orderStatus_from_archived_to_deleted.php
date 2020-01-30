<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m191007_184911_orderStatus_from_archived_to_deleted migration.
 */
class m191007_184911_orderStatus_from_archived_to_deleted extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->renameColumn('{{%commerce_orderstatuses}}', 'dateArchived', 'dateDeleted');
        $this->dropColumn('{{%commerce_orderstatuses}}', 'isArchived');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191007_184911_orderStatus_from_archived_to_deleted cannot be reverted.\n";
        return false;
    }
}
