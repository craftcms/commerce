<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190111_161909_lineItemTotalCanBeNegative migration.
 */
class m190111_161909_lineItemTotalCanBeNegative extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%commerce_lineitems}} alter column [[total]] type numeric(14,4), alter column [[total]] set not null, alter column [[total]] set default 0');
        } else {
            $this->alterColumn('{{%commerce_lineitems}}', 'total', $this->decimal(14, 4)->notNull()->defaultValue(0));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190111_161909_lineItemTotalCanBeNegative cannot be reverted.\n";
        return false;
    }
}
