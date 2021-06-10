<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210608_071647_change_return_cancel_url_column_type migration.
 */
class m210608_071647_change_return_cancel_url_column_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
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
        echo "m210608_071647_change_return_cancel_url_column_type cannot be reverted.\n";
        return false;
    }
}
