<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180401_161901_first_last_name_optional migration.
 */
class m180401_161901_first_last_name_optional extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Now we can set the groupId column to NOT NULL
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_addresses}} alter column [[firstName]] DROP NOT NULL');
            $this->execute('alter table {{%commerce_addresses}} alter column [[lastName]] DROP NOT NULL');
        } else {
            $this->alterColumn('{{%commerce_addresses}}', 'firstName', $this->string()->null());
            $this->alterColumn('{{%commerce_addresses}}', 'lastName', $this->string()->null());
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180401_161901_first_last_name_optional cannot be reverted.\n";
        return false;
    }
}
