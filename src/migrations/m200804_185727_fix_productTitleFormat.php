<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m200804_185727_fix_productTitleFormat migration.
 */
class m200804_185727_fix_productTitleFormat extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_producttypes}} alter column [[productTitleFormat]] drop not null');
        } else {
            $this->alterColumn('{{%commerce_producttypes}}', 'productTitleFormat', $this->string());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200804_185727_fix_productTitleFormat cannot be reverted.\n";
        return false;
    }
}
