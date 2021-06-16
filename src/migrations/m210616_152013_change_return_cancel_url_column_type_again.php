<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210616_152013_change_return_cancel_url_column_type_again migration.
 */
class m210616_152013_change_return_cancel_url_column_type_again extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Running this migration again as a new migration since the column type was wrong in the install migration
        
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_orders}} alter column [[returnUrl]] TYPE TEXT');
            $this->execute('alter table {{%commerce_orders}} alter column [[cancelUrl]] TYPE TEXT');
        } else {
            $this->alterColumn('{{%commerce_orders}}', 'returnUrl', $this->text());
            $this->alterColumn('{{%commerce_orders}}', 'cancelUrl', $this->text());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210616_152013_change_return_cancel_url_column_type_again cannot be reverted.\n";
        return false;
    }
}
